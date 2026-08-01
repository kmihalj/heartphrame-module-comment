(() => {
    'use strict';

    /**
     * HR: Prikazuje tematski prilagodljivu poruku koja ne pomiče sadržaj.
     * EN: Shows a theme-aware message without shifting page content.
     */
    const toast = (section, message) => {
        let container = document.querySelector('.document-comment-toast-container');
        if (!(container instanceof HTMLElement)) {
            container = document.createElement('div');
            container.className = 'document-comment-toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        const item = document.createElement('div');
        item.className = 'document-comment-toast';
        item.setAttribute('role', 'status');
        const text = document.createElement('span');
        text.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', section.dataset.commentCloseLabel || 'Close');
        close.textContent = '×';
        close.addEventListener('click', () => item.remove());
        item.append(text, close);
        container.appendChild(item);
        window.setTimeout(() => item.remove(), 7000);
    };

    /**
     * HR: Dohvaća svježi CSRF token za svaku promjenu.
     * EN: Fetches a fresh CSRF token for every change.
     */
    const csrf = async (section) => {
        const response = await fetch(section.dataset.commentCsrfUrl || '', {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
        });
        const payload = await response.json();
        if (!response.ok || !payload.csrf) {
            throw new Error(payload.error || section.dataset.commentError);
        }

        return payload.csrf;
    };

    /**
     * HR: Šalje jednu URL-encoded radnju i vraća JSON payload.
     * EN: Sends one URL-encoded action and returns its JSON payload.
     */
    const post = async (section, url, values) => {
        const token = await csrf(section);
        const body = new URLSearchParams(values);
        body.set(token.name, token.token);
        const response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
        });
        const payload = await response.json();
        if (!response.ok || payload.ok !== true) {
            throw new Error(payload.error || section.dataset.commentError);
        }

        return payload;
    };

    /**
     * HR: Formatira vrijeme prema jeziku aktualnog dokumenta.
     * EN: Formats timestamps according to the current document language.
     */
    const localizeTimes = (section) => {
        const language = (section.dataset.commentLanguage || 'en').toLowerCase();
        const locale = language.startsWith('hr') ? 'hr-HR' : 'en-GB';
        section.querySelectorAll('.document-comment-time').forEach((time) => {
            if (!(time instanceof HTMLTimeElement)) {
                return;
            }

            const date = new Date(time.dateTime.replace(' ', 'T'));
            if (!Number.isNaN(date.getTime())) {
                time.textContent = new Intl.DateTimeFormat(locale, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(date);
            }
        });
    };

    document.querySelectorAll('.document-comments').forEach((section) => {
        if (section instanceof HTMLElement) {
            localizeTimes(section);
        }
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('document-comment-form')) {
            return;
        }

        event.preventDefault();
        const section = form.closest('.document-comments');
        const submit = form.querySelector('[type="submit"]');
        if (!(section instanceof HTMLElement)) {
            return;
        }

        if (submit instanceof HTMLButtonElement) {
            submit.disabled = true;
        }
        try {
            const values = {};
            new FormData(form).forEach((value, key) => {
                values[key] = String(value);
            });
            await post(section, form.action, values);
            window.location.hash = 'document-comments-title';
            window.location.reload();
        } catch (error) {
            toast(section, error instanceof Error ? error.message : section.dataset.commentError);
        } finally {
            if (submit instanceof HTMLButtonElement) {
                submit.disabled = false;
            }
        }
    });

    document.addEventListener('click', async (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-comment-reaction], [data-comment-report], [data-comment-delete]')
            : null;
        if (!(button instanceof HTMLButtonElement) || button.disabled) {
            return;
        }

        const comment = button.closest('.document-comment');
        const section = button.closest('.document-comments');
        if (!(comment instanceof HTMLElement) || !(section instanceof HTMLElement)) {
            return;
        }

        button.disabled = true;
        try {
            if (button.dataset.commentReaction) {
                const payload = await post(section, section.dataset.commentReactionUrl || '', {
                    comment_uuid: comment.dataset.commentUuid || '',
                    reaction: button.dataset.commentReaction,
                });
                comment.querySelectorAll('[data-comment-reaction]').forEach((reactionButton) => {
                    if (!(reactionButton instanceof HTMLButtonElement)) {
                        return;
                    }

                    const name = reactionButton.dataset.commentReaction || '';
                    reactionButton.classList.toggle('is-active', payload.state.reaction === name);
                    const count = reactionButton.querySelector('[data-comment-reaction-count]');
                    if (count instanceof HTMLElement) {
                        count.textContent = String(payload.state[`${name}_count`] || 0);
                    }
                });
            } else if (button.hasAttribute('data-comment-report')) {
                const payload = await post(section, section.dataset.commentReportUrl || '', {
                    comment_uuid: comment.dataset.commentUuid || '',
                    page_url: `${window.location.pathname}${window.location.search}`,
                });
                toast(section, payload.message);
            } else if (button.hasAttribute('data-comment-delete')) {
                if (!window.confirm(button.title)) {
                    return;
                }

                const payload = await post(section, section.dataset.commentDeleteUrl || '', {
                    comment_uuid: comment.dataset.commentUuid || '',
                });
                comment.remove();
                toast(section, payload.message);
            }
        } catch (error) {
            toast(section, error instanceof Error ? error.message : section.dataset.commentError);
        } finally {
            button.disabled = false;
        }
    });
})();

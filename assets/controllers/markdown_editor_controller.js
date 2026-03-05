import { Controller } from '@hotwired/stimulus';

/**
 * Markdown editor with:
 * - Auto-resizing textarea based on content
 * - Toolbar buttons for simple Markdown formatting (bold, italic, list, etc.)
 */
export default class extends Controller {
    static targets = ['textarea', 'toolbar'];

    connect() {
        if (this.hasTextareaTarget) {
            this.resize();
            this.textareaTarget.addEventListener('input', this.boundResize);
            this.textareaTarget.addEventListener('change', this.boundResize);
            window.addEventListener('resize', this.boundResize);
        }
    }

    disconnect() {
        if (this.hasTextareaTarget) {
            this.textareaTarget.removeEventListener('input', this.boundResize);
            this.textareaTarget.removeEventListener('change', this.boundResize);
            window.removeEventListener('resize', this.boundResize);
        }
    }

    boundResize = () => this.resize();

    resize() {
        if (!this.hasTextareaTarget) return;
        const ta = this.textareaTarget;
        ta.style.height = 'auto';
        ta.style.height = Math.max(ta.scrollHeight, 120) + 'px';
    }

    /**
     * Wrap selection with markdown syntax or insert at cursor.
     * @param {string} before - Text to insert before selection
     * @param {string} after - Text to insert after selection
     * @param {string} [placeholder] - Placeholder when no selection
     */
    wrap(before, after, placeholder = 'texte') {
        const ta = this.textareaTarget;
        if (!ta) return;

        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        const selected = text.substring(start, end);

        const newText = selected
            ? text.substring(0, start) + before + selected + after + text.substring(end)
            : text.substring(0, start) + before + placeholder + after + text.substring(end);

        ta.value = newText;
        ta.focus();

        if (selected) {
            ta.setSelectionRange(start + before.length, end + before.length);
        } else {
            const cursor = start + before.length + placeholder.length;
            ta.setSelectionRange(cursor, cursor);
        }

        ta.dispatchEvent(new Event('input', { bubbles: true }));
        this.resize();
    }

    insertLine(prefix) {
        const ta = this.textareaTarget;
        if (!ta) return;

        const start = ta.selectionStart;
        const text = ta.value;
        const lineStart = text.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = text.indexOf('\n', start);
        const lineEndPos = lineEnd === -1 ? text.length : lineEnd;
        const line = text.substring(lineStart, lineEndPos);

        const newLine = prefix + (line.trim() || '') + '\n';
        const newText = text.substring(0, lineStart) + newLine + text.substring(lineEndPos);

        ta.value = newText;
        const newCursor = lineStart + newLine.length;
        ta.setSelectionRange(newCursor, newCursor);
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        this.resize();
    }

    bold() {
        this.wrap('**', '**', 'gras');
    }

    italic() {
        this.wrap('*', '*', 'italique');
    }

    link() {
        this.wrap('[', '](url)', 'libellé');
    }

    heading() {
        this.insertLine('## ');
    }

    bulletList() {
        this.insertLine('- ');
    }

    orderedList() {
        this.insertLine('1. ');
    }

    blockquote() {
        this.insertLine('> ');
    }

    code() {
        this.wrap('`', '`', 'code');
    }
}

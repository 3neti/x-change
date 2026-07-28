export type RiderContentFormat = 'plain' | 'markdown' | 'html';

export function escapeRiderHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function renderRiderContent(
    value: string,
    format: RiderContentFormat,
): string {
    if (format === 'html') {
        return value;
    }

    if (format === 'markdown') {
        return renderRiderMarkdown(value);
    }

    return `<p>${escapeRiderHtml(value).replaceAll('\n', '<br />')}</p>`;
}

export function renderRiderMarkdown(value: string): string {
    const links: string[] = [];
    const tokenized = value.replace(
        /\[([^\]]+)]\(([^)\s]+)\)/g,
        (match, label: string, href: string): string => {
            if (!isSafeHttpUrl(href)) {
                return label;
            }

            const token = `RIDERLINKTOKEN${links.length}END`;
            links.push(
                `<a href="${escapeRiderHtml(href)}" rel="noopener noreferrer">${escapeRiderHtml(label)}</a>`,
            );

            return token;
        },
    );
    const lines = escapeRiderHtml(tokenized).split(/\r?\n/);
    const blocks: string[] = [];
    let listItems: string[] = [];

    function flushList(): void {
        if (listItems.length === 0) {
            return;
        }

        blocks.push(`<ul>${listItems.join('')}</ul>`);
        listItems = [];
    }

    for (const line of lines) {
        const trimmed = line.trim();
        const listMatch = trimmed.match(/^[-*]\s+(.+)$/);

        if (listMatch) {
            listItems.push(
                `<li>${renderRiderMarkdownInline(listMatch[1])}</li>`,
            );
            continue;
        }

        flushList();

        if (trimmed === '') {
            continue;
        }

        const heading = trimmed.match(/^(#{1,3})\s+(.+)$/);

        if (heading) {
            const level = heading[1].length;
            blocks.push(
                `<h${level}>${renderRiderMarkdownInline(heading[2])}</h${level}>`,
            );
            continue;
        }

        blocks.push(`<p>${renderRiderMarkdownInline(trimmed)}</p>`);
    }

    flushList();

    return links.reduce(
        (html, link, index) => html.replace(`RIDERLINKTOKEN${index}END`, link),
        blocks.join('\n'),
    );
}

function renderRiderMarkdownInline(value: string): string {
    return value
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_]+)__/g, '<strong>$1</strong>')
        .replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>')
        .replace(/(?<!_)_([^_]+)_(?!_)/g, '<em>$1</em>');
}

function isSafeHttpUrl(value: string): boolean {
    try {
        const url = new URL(value);

        return url.protocol === 'https:' || url.protocol === 'http:';
    } catch {
        return false;
    }
}

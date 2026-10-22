<?php

namespace App\Support;

/**
 * Rich text staff type into quotations, cut down to plain formatting tags.
 * Scripts, event attributes (onclick…), styles, links and embedded objects are removed; the text stays.
 */
class SafeHtml
{
    private const TAGS = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'span', 'div', 'hr', 'blockquote', 'small', 'sub', 'sup'];

    private const ATTRIBUTES = ['colspan', 'rowspan', 'align'];

    /** Elements dropped together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'textarea', 'select'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="safe-html-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('safe-html-root');
        if (!$root) {
            return e(strip_tags($html));
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(\DOMNode $node): void
    {
        // Copy the list first: children are removed or replaced while walking
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }

            self::walk($child);

            if (!in_array($tag, self::TAGS, true)) {
                // Unknown tag (a, img, …): keep its text, drop the tag
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = (string) $attribute->value;
                if (!in_array($name, self::ATTRIBUTES, true) || !preg_match('/^[A-Za-z0-9 ]{0,20}$/', $value)) {
                    $child->removeAttribute($attribute->name);
                }
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Communication;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Illuminate\Support\HtmlString;

class SafeHtml
{
    private const TAGS = ['a', 'abbr', 'b', 'big', 'blockquote', 'br', 'caption', 'center', 'code', 'div', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul'];

    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'frame', 'frameset', 'applet', 'title', 'head'];

    private const ATTRIBUTES = ['href', 'src', 'alt', 'title', 'width', 'height', 'align', 'valign', 'colspan', 'rowspan', 'border', 'cellpadding', 'cellspacing', 'style', 'color', 'size', 'face', 'target', 'class', 'id', 'dir', 'lang'];

    public static function clean(?string $html): HtmlString
    {
        $html = (string) $html;
        if ($html === '' || ! preg_match('/[<&]/', $html)) {
            return new HtmlString(e($html));
        }

        $doc = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR, 'UTF-8');
        $body = $doc->body;
        if ($body === null) {
            return new HtmlString('');
        }
        self::walk($body);

        return new HtmlString($body->innerHTML);
    }

    private static function walk(Node $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof Element) {
                $tag = strtolower($node->localName);
                if (in_array($tag, self::DROP, true)) {
                    $node->remove();

                    continue;
                }
                self::walk($node);
                if (! in_array($tag, self::TAGS, true)) {
                    $node->replaceWith(...iterator_to_array($node->childNodes));

                    continue;
                }
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $unsafeUrl = in_array($name, ['href', 'src'], true) && ! self::safeUrl($attribute->value);
                    if (! in_array($name, self::ATTRIBUTES, true) || $unsafeUrl) {
                        $node->removeAttribute($attribute->name);
                    }
                }
            } elseif ($node->nodeType === XML_COMMENT_NODE) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    public static function safeUrl(?string $url): bool
    {
        $url = preg_replace('/[\x00-\x20]+/', '', html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5)) ?? '';

        return ! preg_match('/^([^\/?#]*):/', $url, $m) || in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true);
    }
}

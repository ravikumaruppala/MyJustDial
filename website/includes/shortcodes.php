<?php
/**
 * Shortcodes.
 * Type [form=form_name] in any page text (or inside a blog article's content) and
 * do_shortcodes() replaces it with the form built in Admin > Toolbox > Form Manager.
 */

/** Replace every [form=name] in $content with the rendered form. */
function do_shortcodes(string $content, array $options = []): string {
    return preg_replace_callback('/\[form=([A-Za-z0-9_\-]+)\]/', static function (array $m) use ($options): string {
        return render_form($m[1], $options);
    }, $content);
}

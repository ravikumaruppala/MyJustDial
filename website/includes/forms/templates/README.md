# Form Manager PHP templates

The Form Manager **Custom HTML** field can reference a PHP template by filename. Templates are resolved only from this folder; arbitrary paths or PHP pasted into the database are not executed.

## Profile photo example

1. In Admin → Toolbox → Form Manager, open the form `profile_photo_handler_form`.
2. Add a field of type **Custom HTML** (or edit its existing Custom HTML field).
3. In its **Field Label** field enter exactly:

   `profile-photo-upload.php`

   You may also use `<?php include 'profile-photo-upload.php'; ?>`.
4. Save Changes. The Form Preview pane loads the PHP template output. The same template is rendered on the public member Profile Photo page.
5. Ensure the form remains enabled. Members can then choose JPG/JPEG, GIF, PNG or WEBP images (up to 5 MB), preview them, replace them, or delete the saved image.

The template's file input names are `profile_photo` and `company_logo`. The server validates the extension, actual image content and MIME type; browser-side `accept` attributes are only a convenience.

## Add your own template

Place a PHP file in this directory, then enter only its filename in the **Field Label** field of a Form Manager Custom HTML field. The PHP file is included inside the public form's existing `<form>` element. It may use `e()` and `site_url()` on the public site. Keep templates limited to presentation; save submitted values through the existing form submission pipeline. Do not put credentials or untrusted user input into templates.

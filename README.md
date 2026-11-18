# MyJustDial – Admin Dashboard & Form Manager

This package contains the first assigned module only: the local admin login, admin dashboard, Toolbox navigation, and database-driven Form Manager. It is built with Core PHP, MySQL, Bootstrap 3, HTML, CSS and vanilla JavaScript.

## Included features

- One-time administrator account setup with `password_hash()`.
- Secure admin login, session regeneration, logout and CSRF checks on form posts.
- Dashboard statistics read from the supplied MySQL tables. Missing/empty records are shown as zero or an empty state.
- Toolbox → Form Manager with custom forms and default forms distinguished by `forms.form_action_type`.
- The default forms list includes only records in the supplied SQL database marked with `form_action_type = 'default'`.
- Create and edit custom forms; the nickname dialog can be closed, and Save & Continue opens the new form editor.
- Add fields by clicking or dragging from the searchable field library; reorder them by dragging; edit, duplicate or remove fields; changes appear in the live preview.
- Field types include text, paragraph, email, phone, number, password, URL, dropdown, radio, checkbox, yes/no, date, date/time, upload, rich-text variants, section, custom HTML, alert, hidden, country, state, category, years, price, CAPTCHA, honeypot and submit button.
- Field settings include label, database variable, placeholder, help, description, default value, options, input/display CSS classes, required status, and input/display/lead/email/table/admin visibility controls.
- Country, state and category field previews use the corresponding data in the supplied database when available.
- The responsive builder includes live preview, shortcode copy, form settings, and save/cancel actions.
- Form nickname is converted automatically to a lowercase variable with underscores. Leading/trailing spaces do not create underscores.
- Form and field configurations are stored in the existing `forms` and `form_fields` tables. Form enable/disable state uses the existing `users_meta` table.

## Requirements

- XAMPP with Apache and MySQL/MariaDB.
- PHP 8.0 or newer (PDO MySQL extension enabled).
- A modern browser with HTML5 drag-and-drop support.
- Internet connection for the Bootstrap 3, jQuery and Font Awesome CDN assets. You can replace the CDN references with local copies if the development environment is offline.

## Installation on XAMPP

1. Extract the `MyJustDial` folder into `D:\xampp\htdocs\` (or your XAMPP `htdocs` directory).
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin and create the database `launc58415_directory` (or use the database name already in your environment).
4. Select the database and import `database/launc58415_directory.sql` from this package. The SQL dump is the supplied original database; it contains the original tables and records.
5. If you created a different database name, update `config/config.php`.
6. Open `http://localhost/MyJustDial/`.
7. On first run, the application opens the one-time admin setup page. Create your administrator name, email and password.
8. Sign in. The dashboard opens; use Toolbox → Form Manager to create and manage forms.

### Alternative database import command

From a terminal with MySQL on your PATH:

```bash
mysql -u root -p launc58415_directory < database/launc58415_directory.sql
```

The SQL dump itself does not create the database. Create it first, then import the dump.

## Database notes

The supplied SQL file contains the `forms`, `form_fields`, `users_data`, `users_meta`, `subscription_types`, `data_posts`, `leads`, `website_contacts` and other directory-platform tables. The dump does not define an `administrators` table, even though `admin_roles` and related tables refer to administrators. The app's one-time setup page and `database/02_admin_setup.sql` add this missing table without replacing existing tables.

Form records with `form_action_type = 'default'` are treated as protected default forms. Other form records are shown as custom forms. The supplied dump currently has two form records: `signup_free` (default) and `nxt_wave_form` (custom/widget). No additional default form records are fabricated.

The form editor saves the current field configuration to `form_fields`. Public shortcode rendering and public form submission are intentionally outside this first task, as requested.

## Folder structure

```text
MyJustDial/
├── admin/
│   ├── dashboard.php
│   ├── login.php
│   ├── logout.php
│   ├── setup.php
│   └── toolbox/form-manager/
├── assets/css/ and assets/js/
├── config/
├── database/
├── includes/
├── logs/
├── index.php
└── README.md
```

## Security and developer notes

- Configure a non-root MySQL user for anything beyond local development.
- Do not publish database credentials or the SQL dump to a public repository.
- Delete or restrict access to `admin/setup.php` after the first administrator has been created. The page automatically redirects to login once an administrator exists.
- All database writes use PDO prepared statements. Output is escaped with `htmlspecialchars()` except the explicitly admin-authored custom HTML preview field, which is shown as text in the editor and previewed as text in this first-task implementation.
- The dashboard includes only actual data that can be read from the supplied schema. It does not fabricate visitor analytics or payment totals.
- Remaining sidebar modules are navigation placeholders and are not represented as completed features.


## Visual correction revision
- Dashboard is intentionally limited to the first visible Brilliant Directories screen supplied by the user.
- Dashboard promotional artwork and the directory-site preview are crops from the user's supplied screenshot and are stored locally in `assets/images/`.
- Toolbox is collapsed by default and expands only when clicked.
- Both existing records in the imported `forms` table are displayed as Custom Forms. The legacy `form_action_type` value is not used to classify them.
- The 35 system forms visible in the supplied reference are seeded into a separate `default_forms_catalog` table on the first Form Manager visit. This keeps the system forms separate from the two custom form records.
- New Form opens a styled nickname step. The variable preview is generated live using lowercase letters and underscores, with leading/trailing underscores removed.


## Two-panel Form Builder revision
- Opening a form (Edit, or Save & Continue after New Form) shows a split screen: field widgets on the left, **Form Preview** on the right.
- Each field is one widget: Type of Field (same grouped list as the reference), Required Field? (ON/OFF), Field Label, Database Variable, Add Field +, Actions (Duplicate / Delete) and a gear that reveals Placeholder Text, Field Help Text, Default Field Value, Addtl. Field CSS Class, Outer Element ID, Display Settings (Input View, Display View, Lead Previews, Include In, Table View, with Alt. Field boxes) and Admin Only. For Custom HTML, enter a PHP template filename in the Field Label field.
- Reorder fields by dragging the dark strip with the hamburger icon (mouse or touch), or focus it and use the Up/Down arrow keys.
- **Save Changes** (or Ctrl/Cmd+S) stores everything in `forms` / `form_fields` through `save.php` (JSON response), then redraws the right-hand preview from what was stored. A yellow dot on the button means there are unsaved changes.
- New files: `includes/form_fields.php` (type lists/mapping, field loader), `assets/css/form-builder.css`. Rewritten: `editor.php`, `edit.php`, `save.php`, `assets/js/form-builder.js`.
- Field types are stored with the platform's own names where they exist (Textbox, Email, Phone, textarea, HTML, Button, HoneyPot, ReCaptcha, YesNo, Password); other types keep the editor key. Existing rows keep their original type value unless you change the type.
- Display Settings columns map to: Input View = `field_input_view`, Display View = `field_display_view`, Lead Previews = `field_search_view`, Table View = `field_grid_view`, Include In = `field_email_view`. Alt. Field boxes and Admin Only are kept in `json_meta`; Outer Element ID in `display_div_id`.
- Inter is loaded from Google Fonts for the builder page (falls back to Arial offline).

## UI revision (dashboard, sidebar, login, Form Manager)
- `assets/css/ui-refresh.css` is loaded after `admin.css` and holds all of this revision (admin.css is unchanged). Inter is loaded from Google Fonts, Lato on the login page; Font Awesome 4.7 supplies the icons (both from CDNs, so the browser needs internet).
- Top bar, flash-sale bar and "Build Version" strip use readable sizes. Dashboard fills the full width and height; Basic Website Setup rows and the Explore list stretch evenly; the website preview is a scalable MyJustDial mock (no cropped picture).
- Site name is **MyJustDial** with a logo (`assets/images/myjustdial-logo.svg`) at the top-left of the sidebar, in the dashboard card, page titles and favicon.
- Login follows the supplied "Admin Area Login" design: eye icon shows/hides the password, "Forgot Password? Click to Reset" replaces the first-time link (it opens `admin/forgot-password.php`, a static page; no e-mail reset exists yet). `admin/setup.php` still works until the first admin exists.
- Sidebar: hover shows the sub menu in a fly-out beside the item, click expands it downward. **Collapse** sits under Developer Hub, shrinks the bar to icons (icons change colour on hover, labels appear as tooltips/fly-outs) and the choice is remembered. Sub menus other than Form Manager are placeholder links.
- Form Manager: the Preview column renders each form's real fields as a miniature (`renderMiniForm()` in `includes/form_fields.php`, fitted by `admin.js`); Actions sits to the right of Edit with Font Awesome icons; Status / Type / Sort apply as soon as they are chosen, the search box submits with Enter or the magnifier; Bulk Actions + Apply enable, disable or delete the ticked custom forms (`actions.php?action=bulk`). Sorting also applies to the Default Forms list, and a "Disabled" status filter hides the (always enabled) default forms.


## Day 2 Forms and public pages

The shared bootstrap seeds these forms into the existing `forms` and `form_fields` tables when they are missing:
- `users_basic_contact_form`
- `profile_photo_handler_form`
- `listing_additional_details_form`
- `about_member_form`
- `checkout_free_form`
- `checkout_paid_form`
- `contact_form`, `member_login`, `newsletter_modal_signup`, and `member_review_form` (supporting public-site flows)

The generated shortcodes are `[form=FORM_NAME]`. Public pages render the shortcodes through the existing form renderer and submit through `website/form-submit.php`.

Profile forms require a logged-in member. Their standard columns are updated in `users_data`; additional configured fields are stored in `users_meta`. Profile photo uploads are recorded in `users_photo` and the path is also saved to `users_meta`.

The reference signup screenshot contains a field labelled **“Test 1”**. It appears to be a sample/test field, not a required account attribute, so it is intentionally omitted from the new checkout forms. Checkout asks only for first name, last name, email + confirmation, password + confirmation, and (for paid plans) phone; business name is optional.

Admin and member password reset use secure, one-hour tokens stored in `users_meta`. Configure PHP `mail()` on your local/hosting server for reset emails to be delivered. Paid-plan checkout records the selected subscription on the member account; no payment gateway credentials or payment provider integration were included in the supplied project, so it does not charge a card.

## Profile photo form template

The public member photo form supports an administrator-managed PHP template. In Form Manager, set the Custom HTML field description to `profile-photo-upload.php` (or `<?php include 'profile-photo-upload.php'; ?>`) and save. The template is located at `website/includes/forms/templates/profile-photo-upload.php`. It renders Profile Photo and Profile Logo upload controls, client-side preview, Change and Delete actions. Accepted image formats are JPG/JPEG, GIF, PNG and WEBP, maximum 5 MB. Uploaded photo records are stored in `users_photo` (`type` = `photo` or `logo`) and their paths are mirrored in `users_meta` under `profile_photo` / `company_logo`.

For a custom PHP template, add a `.php` file to `website/includes/forms/templates/` and enter only its filename in the Form Manager Custom HTML field. The Form Manager preview runs the approved template in an authenticated preview frame.

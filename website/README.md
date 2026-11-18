# MyJustDial – public website (`/website`)

Open it from the admin dashboard with **Visit Website**, or go to `http://localhost/MyJustDialNew/website/`.

## Pages (the four in the design)
| File | Page |
|---|---|
| `index.php` | Home – hero search, feature band, newest / featured members, join cards |
| `search.php` | Search Directory – filters, sort, list/grid, pagination, one-member listing (`?listing=ID`) |
| `how-it-works.php` | How It Works |
| `blog.php` | Website Blog – search, list, one article (`?post=ID`) |

| `membership-plans.php` | Membership Plans – every **Members – Join Today / Get Listed Today** button opens it; **SELECT PLAN** opens the sign-up pop-up with that plan |

Helpers: `form-submit.php` (form endpoint), `form.php?name=FORM_NAME` (any admin form on its own page), `logout.php`.

## Folder layout
```
website/
├─ config/site.php            name, phone, which form powers which button, page sizes, upload rules
├─ includes/
│  ├─ bootstrap.php           loads everything (re-uses ../includes/bootstrap.php; never loads admin auth)
│  ├─ helpers.php             small utilities (urls, text, pagination)
│  ├─ data.php                every database read (members, categories, blog)
│  ├─ layout.php              nav bar, header, footer, pop-up window
│  ├─ partials.php            member card, result row, side cards, promo slider
│  ├─ shortcodes.php          [form=name] -> form HTML
│  └─ forms/
│     ├─ form_definitions.php get_form(): admin form from `forms`/`form_fields`, or a built-in fallback
│     ├─ form_renderer.php    render_form(): HTML for every field type
│     ├─ form_validator.php   required / email / phone / url / number / date / options / confirm / upload checks
│     └─ form_saver.php       saves to the right table
├─ assets/ (css, js, images)  styles, behaviour, artwork
├─ uploads/forms/             files sent through "File Upload" fields
└─ database/sample_blog_posts.sql   optional demo articles
```

## Using a form on a page (shortcodes)
Build the form in **Admin → Toolbox → Form Manager**, then put its shortcode (shown in the Shortcode column) in a page or inside a blog article's text:

```php
<?= do_shortcodes('[form=nxt_wave_form]') ?>
```
or just `render_form('nxt_wave_form')`. Visiting `website/form.php?name=nxt_wave_form` also shows it. Forms that an admin **disabled** do not render.

Built-in fallbacks (used only while no admin form with that name exists): `contact_form`, `member_login`, `newsletter_modal_signup`. Create a form with the same name in the admin and yours takes over. `signup_free` (Members – Join Today) comes from your admin form.

## Membership plans
Plans (name, prices, description, features, `subscription_id`) are edited in `config/site.php` -> `'plans'`. Choosing a plan opens the `signup_free` form with the plan attached; the new `users_data` row gets that plan's `subscription_id`. Payment is not connected, so a paid plan is only recorded on the account.

## Where submissions are saved
| Form | Saved to |
|---|---|
| `member_login` (email + password) | nothing is inserted – checks `users_data`, starts a member session |
| form whose `forms.form_table` is `users_data`, or any form with an email **and** password field (sign-up) | new row in `users_data` (password stored with bcrypt, `active` = 2, plan = `free_plan_id`) |
| `form_table` = `leads` | `leads` (`lead_name`, `lead_email`, `lead_message`, `formname` …) |
| everything else (contact, newsletter, your own forms, e.g. `nxt_wave_form`) | `website_contacts` (`NAME`, `EMAIL`, `PHONE`, `COMMENTS`, `CONTACT_TYPE` = form title …) |

Each posted value goes to the column matching its **Database Variable** (aliases such as `yourname → NAME`, `inquiry_email → EMAIL`, `comments → COMMENTS`). Values with no matching column are added to the notes column. Only the three tables above can ever be written to.

> The admin Form Builder currently saves `form_table` empty, so the rules above decide the table. If you later add a table setting to the admin, the website will pick it up automatically.

## Validation and safety
Required fields, email, phone, URL, number, price, date, option lists, `*_confirm` fields (email / password), password length (6+), duplicate sign-up email, file type/size (2 MB), CSRF token, hidden spam-trap field, prepared SQL statements, escaped output, and a 5-attempt login lock (10 minutes).

## Not included / kept static
Google reCAPTCHA (shows a static "I'm not a robot" box), e-mail sending (XAMPP has no mail server – submissions are only stored), password reset by e-mail, member dashboard, rich-text editors (shown as plain text areas), Terms / Privacy text.

## Notes
* The directory lists active members (`users_data.active = 2`) that have a category or a company name.
* Blog articles are `data_posts` rows with `post_status = 1`. Import `database/sample_blog_posts.sql` for demo articles.
* Fonts and icons load from Google Fonts / Font Awesome CDNs, so the browser needs internet.
* The two pictures are artwork in `assets/images/` (SVG). Replace `hero-landscape.svg` / `canyon.svg` with your photos by changing the file names in `assets/css/website.css` (`.hero`) and `img` tags if you like.


## Member dashboard and profile sections
After successful registration or member login, members are signed in and redirected to `member-dashboard.php`.
The dashboard links to four editable sections: `contact-details.php`, `profile-photo.php`, `listing-details.php`, and `about-member.php`.
The profile sections render the forms stored in Admin > Toolbox > Form Manager (`member_contact_details_form`,
`profile_photo_handler_form`, `listing_additional_details_form`, and `about_member_form`). The automatic seeder preserves
admin-edited fields, adds missing starter fields, and removes only unchanged legacy About Me starter fields that no longer
appear in the reference form.
Profile photos and company logos are saved to `users_photo` (`photo` and `logo` types). Standard profile fields are saved
to matching `users_data` columns; additional custom fields are saved to `users_meta`.
The member account pages use a shared dashboard-style shell. The starter profile forms include the contact/location/link
sections shown in the member reference screens; the About Me starter form contains only its rich-text biography field.

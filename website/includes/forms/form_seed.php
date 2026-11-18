<?php
/**
 * Seeds the six Day 2 forms and public-site support forms into the existing forms/form_fields tables.
 * Safe to call on every request: an existing form is never overwritten.
 */
function ensure_day2_forms(PDO $pdo): void {
    if (!tableExists($pdo, 'forms') || !tableExists($pdo, 'form_fields')) return;
    $definitions = [
        'contact_form' => ['Contact Us Form', 'Public contact and member inquiry form', 'form_inquiries', [
            ['text','Name','yourname',true], ['email','Email Address','inquiry_email',true],
            ['tel','Phone Number','phone',false], ['textarea','Message','comments',true], ['submit','Send Message','savebutton',false],
        ]],
        'member_login' => ['Member Login Form', 'Member sign in', 'login', [
            ['email','Email Address','email',true], ['password','Password','password',true], ['submit','Log In Now','savebutton',false],
        ]],
        'newsletter_modal_signup' => ['Newsletter Signup Form', 'Subscribe to website updates', 'website_contacts', [
            ['email','Email Address','email',true], ['submit','Subscribe','savebutton',false],
        ]],
        'member_password_reset_request_form' => ['Member Password Reset Request', 'Request a secure password reset link', 'member_reset_request', [
            ['email','Email Address','email',true], ['submit','Send Reset Link','savebutton',false],
        ]],
        'member_password_reset_form' => ['Member Password Reset Form', 'Choose a new member password', 'member_reset', [
            ['password','New Password','password',true], ['password','Confirm Password','password_confirm',true],
            ['submit','Update Password','savebutton',false],
        ]],
        'member_review_form' => ['Member Review Form', 'Write a review for a member', 'users_reviews', [
            ['text','Review Title','review_title',true], ['textarea','Your Review','review_description',true],
            ['radio','Overall Rating','rating_overall',true], ['radio','Service Rating','rating_service',true],
            ['radio','Responsiveness','rating_response',true], ['radio','Expertise','rating_expertise',true],
            ['radio','Results','rating_results',true], ['select','Gender','gender',true], ['text','Your Name or Company','review_name',true],
            ['email','Email Address','review_email',true], ['text','Postal Code','review_zip',false],
            ['submit','Submit Review','savebutton',false],
        ]],
        'users_basic_contact_form' => ['Users Basic / Contact Form', 'Basic contact details', 'website_contacts', [
            ['text','First Name','first_name',true], ['text','Last Name','last_name',true],
            ['email','Email Address','email',true], ['tel','Phone Number','phone_number',false],
            ['text','City','city',false], ['textarea','Message','message',true], ['submit','Send Message','savebutton',false],
        ]],
        'profile_photo_handler_form' => ['Profile Photo Handler Form', 'Upload your profile photo and company logo', 'users_data', [
            ['help_alert','ACCEPTED FORMATS: JPG, GIF, PNG & WEBP','photo_format_notice',false],
            ['section','Profile Photo and Company Logo','photo_upload_section',false], ['file','Profile Photo','profile_photo',false],
            ['file','Company Logo','company_logo',false], ['submit','Continue to Next Section','savebutton',false],
        ]],
        'member_contact_details_form' => ['Member Contact Details Form', 'Member contact information and website links', 'users_data', [
            ['help_alert','To activate your listing, please fill in all of the required fields below.','contact_required_notice',false],
            ['select','Type of Listing','listing_type',true],
            ['top_category','Best Describes You','profession_id',true],
            ['text','First Name','first_name',true], ['text','Last Name','last_name',true],
            ['email','Email Address','email',true], ['tel','Phone Number','phone_number',false],
            ['text','Company Name','company',false], ['text','Your Position','position',false],
            ['textarea','Short Description','search_description',false],
            ['section','Where Are You Located?','location_section',false],
            ['text','Map Location','gmap',false], ['text','Address Line 1','address1',false],
            ['text','Address Line 2','address2',false], ['text','City','city',false],
            ['state','State','state_ln',false], ['country','Country','country_ln',false],
            ['text','Postal Code','zip_code',false],
            ['section','Enter Website Links','website_links_section',false],
            ['help_alert','Links must include http:// or https://','website_links_notice',false],
            ['url','Website','website',false],
            ['url','Booking Link','booking_link',false], ['url','Blog','blog',false],
            ['url','Facebook','facebook',false], ['url','X','twitter',false],
            ['url','LinkedIn','linkedin',false], ['url','YouTube','youtube',false],
            ['url','Instagram','instagram',false], ['url','Pinterest','pinterest',false],
            ['url','TikTok','tiktok',false], ['url','Snapchat','snapchat',false],
            ['tel','WhatsApp','whatsapp',false], ['submit','Save Contact Details','savebutton',false],
        ]],
        'listing_additional_details_form' => ['Listing / Additional Details Form', 'Add business listing details', 'users_data', [
            ['file','Upload CV or Brochure','cv',false], ['text','Personal Quote / Motto','quote',false],
            ['years','Year Established','year_established',false], ['textarea','Hours of Operation','hours_of_operation',false],
            ['textarea','Accepted Forms of Payments','accepted_payments',false], ['textarea','Credentials','credentials',false],
            ['textarea','Honors & Awards','awards',false], ['submit','Save Listing Details','savebutton',false],
        ]],
        'about_member_form' => ['About Member Form', 'Tell visitors about yourself', 'users_data', [
            ['help_alert','Include additional details about your specialties','about_help_notice',false],
            ['richtext','About Me','about_me',false], ['submit','Save & Continue','savebutton',false],
        ]],
        'checkout_free_form' => ['Checkout Form - Free', 'Create your free member account', 'users_data', [
            ['section','Create Your Free Account','',false], ['text','First Name','first_name',true],
            ['text','Last Name','last_name',true], ['email','Email Address','email',true],
            ['email','Confirm Email Address','email_confirm',true], ['password','Create Password','password',true],
            ['password','Confirm Password','password_confirm',true], ['tel','Phone Number','phone_number',false],
            ['submit','Create My Free Account','savebutton',false],
        ]],
        'checkout_paid_form' => ['Checkout Form - Paid', 'Create your paid membership account', 'users_data', [
            ['section','Create Your Membership Account','',false], ['text','First Name','first_name',true],
            ['text','Last Name','last_name',true], ['email','Email Address','email',true],
            ['email','Confirm Email Address','email_confirm',true], ['password','Create Password','password',true],
            ['password','Confirm Password','password_confirm',true], ['tel','Phone Number','phone_number',true],
            ['text','Business / Company Name','company',false], ['submit','Continue','savebutton',false],
        ]],
    ];
    $find = $pdo->prepare('SELECT form_id FROM forms WHERE form_name=? LIMIT 1');
    $insertForm = $pdo->prepare("INSERT INTO forms
      (form_name,form_desc,form_url,form_target,form_action,form_database,form_table,table_index,
       form_email_recipient,form_title,form_email_on,email_template,email_admin,email_from,email_template_admin,
       form_class,form_style,form_element_id,short_code,form_layout,trigger_data_flow,data_flow_name,
       form_action_type,form_action_div)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insertField = $pdo->prepare("INSERT INTO form_fields
      (form_name,field_text,field_name,field_type,field_order,field_sdesc,field_ldesc,field_placeholder,
       field_required,field_options,input_class,display_class,default_value,field_display_view,field_input_view,
       field_grid_view,field_email_view,form_section,field_search_view,display_div_id,input_div_id,field_icon,json_meta)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($definitions as $name => [$title,$desc,$table,$fields]) {
        $find->execute([$name]);
        if ($find->fetchColumn()) continue;
        $insertForm->execute([
            $name,$desc,'','','post','',$table,'','',''.$title,0,'',0,'','',
            'form-control','','','[form='.$name.']','bootstrapvertical','','','widget',''
        ]);
        foreach ($fields as $i => [$type,$label,$fieldName,$required]) {
            $stored = formFieldStoredType($type);
            $placeholders = [
                'profession_id' => '(Select from List)',
                'year_established' => 'Select From List',
                'gmap' => 'Enter a location: 350 Fifth Avenue, New York, NY 10118',
                'search_description' => 'Tell us about yourself...',
                'website' => 'https://www.mywebsite.com',
                'booking_link' => 'Enter external link where visitors can book appointments with you',
                'blog' => 'https://www.myblog.com',
                'facebook' => 'https://www.facebook.com/your_name',
                'twitter' => 'https://www.twitter.com/your_name',
                'linkedin' => 'https://www.linkedin.com/in/your_name',
                'youtube' => 'https://www.youtube.com/your_name',
                'instagram' => 'https://www.instagram.com/your_name',
                'pinterest' => 'https://www.pinterest.com/your_name',
                'tiktok' => 'https://www.tiktok.com/@username',
                'snapchat' => 'https://www.snapchat.com/add/username',
                'whatsapp' => 'Full phone number in international format: https://wa.me/1XXXXXXXXXX',
            ];
            $help = $fieldName === 'search_description' ? 'Displays under your name on search result pages (170 Character Limit)' :
                ($fieldName === 'profession_id' ? 'Choose your main category, then select sub-level categories in the Additional Details tab' : '');
            $default = $fieldName === 'listing_type' ? 'Company / Organization' : '';
            $insertField->execute([
                $name,$label,$fieldName,$stored,$i+1,
                $help,
                '',
                $placeholders[$fieldName] ?? ($fieldName==='email'?'name@example.com':($fieldName==='tel'?'Phone number':'')),
                $required?1:0,($fieldName==='gender'?"male\nfemale":($fieldName==='listing_type'?"Individual\nCompany / Organization":(strpos($fieldName,'rating_')===0?"1\n2\n3\n4\n5":''))),'form-control','',$default,'1','1','1','1','',0,'','','',
                json_encode(['adminOnly'=>false], JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    // Upgrade forms created by earlier project versions without overwriting any
    // field an administrator has already edited in Form Manager.
    $additions = [
        'profile_photo_handler_form' => [
            ['file','Company Logo','company_logo',false],
        ],
        'listing_additional_details_form' => [
            ['file','Upload CV or Brochure','cv',false], ['text','Personal Quote / Motto','quote',false],
            ['years','Year Established','year_established',false], ['textarea','Hours of Operation','hours_of_operation',false],
            ['textarea','Accepted Forms of Payments','accepted_payments',false], ['textarea','Credentials','credentials',false],
            ['textarea','Honors & Awards','awards',false],
        ],
    ];
    $existsField = $pdo->prepare('SELECT field_id FROM form_fields WHERE form_name=? AND field_name=? LIMIT 1');
    $appendField = $pdo->prepare("INSERT INTO form_fields
      (form_name,field_text,field_name,field_type,field_order,field_sdesc,field_ldesc,field_placeholder,
       field_required,field_options,input_class,display_class,default_value,field_display_view,field_input_view,
       field_grid_view,field_email_view,form_section,field_search_view,display_div_id,input_div_id,field_icon,json_meta)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($additions as $formName => $fields) {
        $next = $pdo->prepare('SELECT COALESCE(MAX(field_order),0)+1 FROM form_fields WHERE form_name=?');
        $next->execute([$formName]); $order = (int)$next->fetchColumn();
        foreach ($fields as [$type,$label,$fieldName,$required]) {
            $existsField->execute([$formName,$fieldName]);
            if ($existsField->fetchColumn()) continue;
            $appendField->execute([
                $formName,$label,$fieldName,formFieldStoredType($type),$order++,'','','',
                $required?1:0,'','form-control','','','1','1','1','1','',0,'','','',
                json_encode(['adminOnly'=>false], JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    $decorations = [
        'member_contact_details_form' => [
            ['help_alert','To activate your listing, please fill in all of the required fields below.','contact_required_notice',''],
            ['section','Where Are You Located?','location_section','gmap'],
            ['section','Enter Website Links','website_links_section','website'],
            ['help_alert','Links must include http:// or https://','website_links_notice','website'],
        ],
        'profile_photo_handler_form' => [
            ['html','Profile Photo Upload Template','photo_upload_template','profile_photo'],
            ['help_alert','ACCEPTED FORMATS: JPG, GIF, PNG & WEBP','photo_format_notice','profile_photo'],
        ],
        'about_member_form' => [
            ['help_alert','Include additional details about your specialties','about_help_notice','about_me'],
        ],
    ];
    foreach ($decorations as $formName => $items) {
        $next->execute([$formName]); $order = (int)$next->fetchColumn();
        foreach ($items as [$type,$label,$fieldName,$anchor]) {
            $existsField->execute([$formName,$fieldName]);
            if ($existsField->fetchColumn()) continue;
            $appendField->execute([
                $formName,$label,$fieldName,formFieldStoredType($type),$order++,'','','',0,'',
                'form-control','','','1','1','1','1','',0,'','','',
                json_encode(['adminOnly'=>false], JSON_UNESCAPED_UNICODE)
            ]);
        }

        $query = $pdo->prepare('SELECT field_id,field_name,field_order FROM form_fields WHERE form_name=? ORDER BY field_order,field_id');
        $query->execute([$formName]); $rows = $query->fetchAll();
        $before = [];
        foreach ($items as $item) {
            if ($item[3] !== '') $before[$item[3]][] = $item[2];
        }
        $decorNames = array_column($items, 2);
        $ordered = [];
        foreach ($rows as $row) {
            if (isset($before[$row['field_name']])) {
                foreach ($before[$row['field_name']] as $name) {
                    if (in_array($name, $decorNames, true)) {
                        foreach ($rows as $decoration) {
                            if ($decoration['field_name'] === $name) { $ordered[] = (int)$decoration['field_id']; break; }
                        }
                    }
                }
            }
            if (!in_array($row['field_name'], $decorNames, true)) $ordered[] = (int)$row['field_id'];
        }
        foreach ($items as $item) {
            if ($item[3] === '') {
                foreach ($rows as $decoration) {
                    if ($decoration['field_name'] === $item[2] && !in_array((int)$decoration['field_id'], $ordered, true)) {
                        array_unshift($ordered, (int)$decoration['field_id']);
                    }
                }
            }
        }
        $setOrder = $pdo->prepare('UPDATE form_fields SET field_order=? WHERE field_id=?');
        $positions = [];
        foreach ($ordered as $position => $fieldId) $positions[$fieldId] = $position + 1;
        foreach ($rows as $row) {
            $fieldId = (int)$row['field_id'];
            if (isset($positions[$fieldId]) && (int)$row['field_order'] !== $positions[$fieldId]) {
                $setOrder->execute([$positions[$fieldId],$fieldId]);
            }
        }
    }

    // The profile image UI is rendered from this Form Manager Custom HTML template.
    // Preserve any filename an administrator has already selected.
    $templateField = $pdo->prepare("UPDATE form_fields SET field_ldesc='profile-photo-upload.php' WHERE form_name='profile_photo_handler_form' AND field_name='photo_upload_template' AND (field_ldesc IS NULL OR field_ldesc='')");
    $templateField->execute();

    // Remove only the obsolete starter fields created by the previous About Me definition.
    $pdo->exec("DELETE FROM form_fields WHERE form_name='about_member_form' AND
      ((field_name='experience' AND field_text='Years of Experience') OR
       (field_name='affiliation' AND field_text='Affiliations') OR
       (field_name='awards' AND field_text='Awards') OR
       (field_name='quote' AND field_text='Professional Title'))");
    $listingStarterFields = [
        'company'=>'Business / Company Name','position'=>'Job Title / Position','phone_number'=>'Phone Number',
        'address1'=>'Address','city'=>'City','state_ln'=>'State','zip_code'=>'Postal Code',
        'country_ln'=>'Country','website'=>'Website','profession_id'=>'Professional Category ID',
    ];
    $removeListingField = $pdo->prepare('DELETE FROM form_fields WHERE form_name=? AND field_name=? AND field_text=?');
    foreach ($listingStarterFields as $fieldName => $label) {
        $removeListingField->execute(['listing_additional_details_form',$fieldName,$label]);
    }
    $pdo->exec("DELETE FROM form_fields WHERE form_name='profile_photo_handler_form' AND field_type IN ('section','Section') AND field_text='Profile Photo'");
    $pdo->exec("UPDATE form_fields SET field_text='Save & Continue' WHERE form_name='about_member_form'
      AND field_name='savebutton' AND field_text='Save About Information'");
    $pdo->exec("UPDATE form_fields SET field_text='Save & Continue' WHERE form_name='member_contact_details_form'
      AND field_name='savebutton' AND field_text='Save Contact Details'");
    $pdo->exec("UPDATE form_fields SET field_text='Save & Continue' WHERE form_name='listing_additional_details_form'
      AND field_name='savebutton' AND field_text='Save Listing Details'");
    $pdo->exec("UPDATE form_fields SET field_text='Continue to Next Section' WHERE form_name='profile_photo_handler_form'
      AND field_name='savebutton' AND field_text IN ('Save Photos','Upload Photo')");
    $pdo->exec("UPDATE form_fields SET field_placeholder='Enter a location: 350 Fifth Avenue, New York, NY 10118'
      WHERE form_name='member_contact_details_form' AND field_name='gmap'
      AND (field_placeholder='' OR field_placeholder='Enter a location or address')");
    $pdo->exec("UPDATE form_fields SET field_sdesc='Displays under your name on search result pages (170 Character Limit)'
      WHERE form_name='member_contact_details_form' AND field_name='search_description' AND field_sdesc=''");
    $pdo->exec("UPDATE form_fields SET field_placeholder='(Select from List)'
      WHERE form_name='member_contact_details_form' AND field_name='profession_id' AND field_placeholder=''");
    $pdo->exec("UPDATE form_fields SET default_value='Company / Organization'
      WHERE form_name='member_contact_details_form' AND field_name='listing_type' AND default_value=''");
    $pdo->exec("UPDATE form_fields SET field_placeholder='Select From List'
      WHERE form_name='listing_additional_details_form' AND field_name='year_established' AND field_placeholder=''");
    $contactPlaceholders = [
        'website'=>'https://www.mywebsite.com',
        'booking_link'=>'Enter external link where visitors can book appointments with you',
        'blog'=>'https://www.myblog.com',
        'facebook'=>'https://www.facebook.com/your_name',
        'twitter'=>'https://www.twitter.com/your_name',
        'linkedin'=>'https://www.linkedin.com/in/your_name',
        'youtube'=>'https://www.youtube.com/your_name',
        'instagram'=>'https://www.instagram.com/your_name',
        'pinterest'=>'https://www.pinterest.com/your_name',
        'tiktok'=>'https://www.tiktok.com/@username',
        'snapchat'=>'https://www.snapchat.com/add/username',
        'whatsapp'=>'Full phone number in international format: https://wa.me/1XXXXXXXXXX',
    ];
    $setPlaceholder = $pdo->prepare("UPDATE form_fields SET field_placeholder=? WHERE form_name='member_contact_details_form' AND field_name=? AND (field_placeholder='' OR field_placeholder='https://www.example.com')");
    foreach ($contactPlaceholders as $fieldName => $placeholder) $setPlaceholder->execute([$placeholder,$fieldName]);
    $pdo->exec("UPDATE form_fields SET field_placeholder='Enter a location: 350 Fifth Avenue, New York, NY 10118'
      WHERE form_name='member_contact_details_form' AND field_name='gmap' AND field_placeholder='Enter a location or address'");

    foreach (array_keys($additions) as $formName) {
        $submitQuery=$pdo->prepare("SELECT field_id FROM form_fields WHERE form_name=? AND field_type IN ('Button','submit') ORDER BY field_order DESC LIMIT 1");
        $submitQuery->execute([$formName]); $submitId=(int)$submitQuery->fetchColumn();
        if ($submitId>0) {
            $maxQuery=$pdo->prepare("SELECT COALESCE(MAX(field_order),0)+1 FROM form_fields WHERE form_name=? AND field_type NOT IN ('Button','submit')");
            $maxQuery->execute([$formName]); $lastOrder=(int)$maxQuery->fetchColumn();
            $currentOrder=$pdo->prepare('SELECT field_order FROM form_fields WHERE field_id=?');$currentOrder->execute([$submitId]);if((int)$currentOrder->fetchColumn()<$lastOrder)$pdo->prepare('UPDATE form_fields SET field_order=? WHERE field_id=?')->execute([$lastOrder,$submitId]);
        }
    }
}

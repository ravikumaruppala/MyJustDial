<?php
/**
 * Website-facing settings.
 * Everything that is "content" (name, phone, which admin forms power which button) lives here,
 * so the page files stay free of hard-coded values.
 */
return [
    // Branding shown in the header, footer and page titles.
    'name'      => 'MyJustDial',
    'tagline'   => 'The All-In-One Business Search Directory',
    'phone'     => '1-800-555-5555',
    'currency'  => '₹',          // symbol shown by "Price Field" inputs

    // Shortcode names (form_name in the `forms` table) used by the buttons in the layout.
    // Create a form with the same name in Admin > Toolbox > Form Manager and it replaces the built-in one.
    'forms' => [
        'contact'    => 'contact_form',            // header "Contact" + footer "Contact Us" + "Send Message"
        'login'      => 'member_login',            // header "Login" + footer "Member Login"
        'join'       => 'signup_free',             // every "Members - Join Today" / "Get Listed Today" button
        'newsletter' => 'newsletter_modal_signup', // "Click to Subscribe" + footer envelope icon
    ],

    // Directory settings.
    'members_per_page' => 10,   // results per page on Search Directory
    'posts_per_page'   => 9,    // articles per page on Website Blog
    'home_members'     => 3,    // cards in "Our Newest Members"
    'member_active'    => 2,    // users_data.active value that means "active account"
    'free_plan_id'     => 1,    // subscription_types.subscription_id given to new sign-ups

    // Membership plans shown on membership-plans.php. 'subscription_id' is the users_data.subscription_id
    // a new member receives when choosing the plan (1-3 are "Membership Plan 1-3" in subscription_types).
    // Use {site} in a description to print the website name. Payment is not connected: choosing a plan
    // only records it on the new account.
    'plans' => [
        'premium' => [
            'name' => 'Premium', 'monthly' => 99, 'yearly' => 999, 'subscription_id' => 3,
            'description' => 'A premium membership designed for dentists and dental clinics that want maximum visibility and more opportunities to connect with patients.',
            'features' => ['Professional Dentist Profile', 'Clinic/Hospital Information', 'Contact Information', 'Dental Services Listing', 'Basic Profile Visibility',
                           'Location & Address', 'Phone & Email Display', 'Patient Inquiry Requests', 'Appear in Dentist Search'],
        ],
        'standard' => [
            'name' => 'Standard', 'monthly' => 79, 'yearly' => 899, 'subscription_id' => 2,
            'description' => 'A standard membership designed for dentists and dental clinics that want professional visibility and reliable opportunities to connect with patients.',
            'features' => ['Everything in Basic Dentist', 'Enhanced Dentist Profile', 'Featured Profile Visibility', 'Multiple Dental Services', 'Clinic Photos & Logo',
                           'Website Link', 'Priority Search Placement', 'Patient Lead Opportunities', 'Appointment Inquiry Support', 'Professional Profile Badge'],
        ],
        'free' => [
            'name' => 'Free', 'monthly' => 0, 'yearly' => 0, 'subscription_id' => 1,
            'description' => 'A free membership for dentists and dental clinics to create a basic listing and begin building visibility on {site}.',
            'features' => ['Everything in Basic Dentist', 'Everything in Professional Dentist', 'Featured Dentist Profile', 'Priority Search Placement', 'Unlimited Dental Services', 'Clinic Photos & Logo',
                           'Website & Social Media Links', 'Patient Inquiry Requests', 'Appointment Inquiry Support', 'Verified Dentist Badge', 'Enhanced Profile Visibility', 'Annual Membership Savings'],
        ],
    ],

    // File uploads accepted by "File Upload Field" inputs of any shortcode form.
    'upload_max_bytes' => 2097152, // 2 MB
    'upload_allowed'   => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
];

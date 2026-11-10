-- OPTIONAL: three sample blog articles so the "Website Blog" page has something to show.
-- Import with phpMyAdmin (database launc58415_directory) or:  mysql -u root launc58415_directory < sample_blog_posts.sql
-- The second and third articles show the shortcodes in action: [form=nxt_wave_form] and [form=contact_form]
-- become real forms on the page. Delete these rows any time; the blog then shows "no results" again.
SET SESSION sql_mode = '';

INSERT INTO `data_posts`
  (`post_title`, `post_caption`, `post_content`, `post_category`, `post_type`, `data_type`, `post_date`, `post_live_date`, `post_status`, `post_author`, `post_tags`, `user_id`)
VALUES
  ('How to Choose a Trusted Local Business',
   'Five simple checks before you hire.',
   '<p>Finding a reliable business takes a few minutes of research. Start by reading the member listing, compare two or three options, and always send a message with your questions before you decide.</p><p><strong>Quick checklist:</strong></p><ul><li>Check the location and service area</li><li>Read the description and awards</li><li>Ask for a clear quote</li><li>Look for recent reviews</li><li>Contact more than one member</li></ul>',
   'Tips', 'post', 'blog', DATE_FORMAT(NOW(), '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), 1, 'MyJustDial Team', 'tips,hiring', 5),
  ('Tell Us About Your Project',
   'Use the form below to reach our team.',
   '<p>Have a question about listing your business or finding the right member? Fill in the form below and we will get back to you.</p>[form=nxt_wave_form]',
   'News', 'post', 'blog', DATE_FORMAT(NOW(), '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), 1, 'MyJustDial Team', 'contact', 5),
  ('Get in Touch',
   'A contact form placed inside an article.',
   '<p>This article embeds the built-in contact form with a single shortcode.</p>[form=contact_form]',
   'News', 'post', 'blog', DATE_FORMAT(NOW(), '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), 1, 'MyJustDial Team', 'contact', 5);

<?php
require __DIR__ . '/includes/bootstrap.php';
$member = require_member_account();
render_header(['title'=>'Contact Details','member_portal'=>true,'member_section'=>'contact']);
?>
<section class="member-form-page member-contact-page">
  <div class="member-form-kicker">Basic Information</div>
  <div class="card form-card"><h1>Enter Contact Information Below</h1><?= render_form('member_contact_details_form', ['member_id'=>$member['user_id']]) ?></div>
  <script>
  (function(){
    var input=document.querySelector('input[name="gmap"]');
    if(!input)return;
    var wrap=document.createElement('div');wrap.className='member-map-preview';
    var frame=document.createElement('iframe');frame.loading='lazy';frame.title='Location map';
    function update(){frame.src='https://maps.google.com/maps?q='+encodeURIComponent(input.value||'India')+'&output=embed';}
    wrap.appendChild(frame);input.parentNode.appendChild(wrap);update();
    input.addEventListener('change',update);input.addEventListener('blur',update);
  })();
  </script>
</section>
<?php render_footer(); ?>

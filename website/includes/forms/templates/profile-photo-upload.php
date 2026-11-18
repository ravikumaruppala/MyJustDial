<?php
/** Example safe Form Manager PHP template. Inputs must match Form Manager field names. */
$photoPath = (string)($mjd_member_photos['photo'] ?? '');
$logoPath = (string)($mjd_member_photos['logo'] ?? '');
$photoUrl = $photoPath !== '' ? (preg_match('#^https?://#i', $photoPath) ? $photoPath : site_url($photoPath)) : site_url('assets/images/avatar.svg');
$logoUrl = $logoPath !== '' ? (preg_match('#^https?://#i', $logoPath) ? $logoPath : site_url($logoPath)) : site_url('assets/images/logo.svg');
?>
<style>
.mjd-photo-manager{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;margin:14px 0}.mjd-photo-box{border:1px solid #e3e3e3;padding:18px 14px;text-align:center;background:#fff}.mjd-photo-box h3{margin:0 0 8px;font-size:21px}.mjd-photo-box p{margin:0 0 12px}.mjd-photo-preview{width:min(100%,300px);height:300px;object-fit:contain;border:1px solid #ddd;display:block;margin:0 auto 8px;background:#fff}.mjd-photo-actions{display:flex;gap:5px;justify-content:center}.mjd-photo-actions button{border:0;color:#fff;background:#050505;padding:12px 18px;cursor:pointer;min-width:42%}.mjd-photo-actions .mjd-photo-delete{background:#f00000}.mjd-photo-file{position:absolute!important;width:1px!important;height:1px!important;opacity:0!important;overflow:hidden!important}.mjd-photo-error{color:#c00;min-height:20px;margin:6px 0}.mjd-photo-format{background:#050505;color:#fff;padding:12px 8px;margin:8px 0 18px;font-weight:bold}@media(max-width:650px){.mjd-photo-manager{grid-template-columns:1fr}.mjd-photo-preview{height:240px}}
</style>
<div class="mjd-photo-format">ACCEPTED FORMATS: JPG, GIF, PNG &amp; WEBP</div>
<div class="mjd-photo-manager" data-mjd-photo-manager>
  <section class="mjd-photo-box" data-photo-kind="photo">
    <h3>Profile Photo</h3><p>Recommended Size: 400x400 pixels</p>
    <img class="mjd-photo-preview" data-photo-preview src="<?= e($photoUrl) ?>" alt="Profile Photo preview">
    <input class="mjd-photo-file" type="file" name="profile_photo" accept=".jpg,.jpeg,.gif,.png,.webp,image/jpeg,image/gif,image/png,image/webp" data-photo-input>
    <input type="hidden" name="mjd_delete_profile_photo" value="0" data-photo-delete-flag>
    <div class="mjd-photo-error mjd-error" data-for="profile_photo" data-photo-error role="alert"><?= e((string)($mjd_form_errors['profile_photo'] ?? '')) ?></div>
    <div class="mjd-photo-actions"><button type="button" data-photo-change>Change Photo</button><button type="button" class="mjd-photo-delete" data-photo-delete>⊗ Delete</button></div>
  </section>
  <section class="mjd-photo-box" data-photo-kind="logo">
    <h3>Profile Logo</h3><p>Recommended Size: 400x400 pixels</p>
    <img class="mjd-photo-preview" data-photo-preview src="<?= e($logoUrl) ?>" alt="Profile Logo preview">
    <input class="mjd-photo-file" type="file" name="company_logo" accept=".jpg,.jpeg,.gif,.png,.webp,image/jpeg,image/gif,image/png,image/webp" data-photo-input>
    <input type="hidden" name="mjd_delete_company_logo" value="0" data-photo-delete-flag>
    <div class="mjd-photo-error mjd-error" data-for="company_logo" data-photo-error role="alert"><?= e((string)($mjd_form_errors['company_logo'] ?? '')) ?></div>
    <div class="mjd-photo-actions"><button type="button" data-photo-change>Change Logo</button><button type="button" class="mjd-photo-delete" data-photo-delete>⊗ Delete</button></div>
  </section>
</div>
<script>
(function(){
  document.querySelectorAll('[data-mjd-photo-manager]').forEach(function(manager){
    manager.querySelectorAll('[data-photo-kind]').forEach(function(box){
      var input=box.querySelector('[data-photo-input]'), preview=box.querySelector('[data-photo-preview]'), error=box.querySelector('[data-photo-error]'), del=box.querySelector('[data-photo-delete-flag]');
      box.querySelector('[data-photo-change]').addEventListener('click',function(){input.click();});
      input.addEventListener('change',function(){
        error.textContent=''; var file=input.files && input.files[0]; if(!file)return;
        var ext=(file.name.split('.').pop()||'').toLowerCase();
        if(!['jpg','jpeg','gif','png','webp'].includes(ext) || !['image/jpeg','image/gif','image/png','image/webp'].includes(file.type)){
          input.value=''; error.textContent='Invalid format. Please upload JPG, GIF, PNG or WEBP only.'; return;
        }
        if(file.size>5*1024*1024){input.value='';error.textContent='Image must be 5 MB or smaller.';return;}
        var reader=new FileReader(); reader.onload=function(e){preview.src=e.target.result;}; reader.readAsDataURL(file); del.value='0';
      });
      box.querySelector('[data-photo-delete]').addEventListener('click',function(){
        input.value=''; del.value='1'; preview.src='<?= e(site_url('assets/images/avatar.svg')) ?>'; error.textContent='';
      });
    });
  });
})();
</script>

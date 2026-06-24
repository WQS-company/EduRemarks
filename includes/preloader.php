<!-- Elite World-Class Preloader Component -->
<?php
$preloader_prefix = $path_prefix ?? '';
// Detection logic for subdirectories (student, admin, super_admin)
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
if ($current_dir === 'student' || $current_dir === 'admin' || $current_dir === 'super_admin' || $current_dir === 'staff' || $current_dir === 'user') {
    $preloader_prefix = '../';
}
?>
<div id="preloader">
    <div class="loader-content">
        <img src="<?php echo $preloader_prefix . get_setting('platform_logo', 'img/logo.png'); ?>" alt="EduRemarks" class="loader-logo">
        <div class="loader-track">
            <div class="loader-bar"></div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.classList.add('loaded');
            // Allow animation to complete before removing from DOM flow
            setTimeout(() => { 
                preloader.style.display = 'none'; 
            }, 900);
        }
    });
</script>

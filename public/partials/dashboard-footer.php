    </main>
</div><!-- /.sidebar-main -->

    <?php /* See footer.php: a page may render both footers, and a classic script
     included twice re-declares its top-level const, killing the whole file.
     This set is skipped when footer.php has already emitted it. */ ?>
<?php if (!defined('ANIMAL_ASSETS_EMITTED')): define('ANIMAL_ASSETS_EMITTED', true); ?>
    <script src="<?= e(asset('assets/js/motion.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/toast.js')) ?>" defer></script>
  <script src="<?= e(asset('assets/js/cart-ajax.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/save-ajax.js')) ?>" defer></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
<?php endif; ?>

</body>
</html>

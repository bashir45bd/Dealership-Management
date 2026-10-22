<?php
/**
 * Maruf Traders - Master Page Footer
 */
defined('APP_INIT') or define('APP_INIT', true);
?>
            </div><!-- /.content-body -->

            <!-- Footer Area -->
            <footer class="text-center py-3 border-top border-secondary border-opacity-10 text-muted" style="font-size: 0.8rem; background: var(--bg-navbar);">
                <div>&copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars(getSetting('business_name', APP_NAME)); ?></strong> — All Rights Reserved. Dealership Management System.</div>
            </footer>
        </div><!-- /.main-content -->
    </div><!-- /.app-wrapper -->

    <!-- Mobile Drawer Backdrop -->
    <div class="sidebar-backdrop"></div>

    <!-- Core Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Application Scripts -->
    <script src="<?php echo BASE_URL; ?>/assets/js/common.js?v=<?php echo APP_VERSION; ?>"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>

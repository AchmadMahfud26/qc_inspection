<?php
// includes/footer.php
?>
    </div> <!-- /.main-content -->
    </div> <!-- /.container-fluid -->

<footer class="bg-light text-muted py-3 mt-4">
    <div class="footer-inner">
        <div>© <?php echo date('Y'); ?> QC INSPECTION | Quality Control Inspection & Traceability System</div>
    </div>
</footer>

<!-- JS: Bootstrap 5 and dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Sidebar toggle logic
(function(){
    const body = document.body;
    const toggle = document.getElementById('sidebarToggle');
    const miniToggle = document.getElementById('sidebarMiniToggle');
    const mobileClose = document.getElementById('mobileSidebarClose');
    const sidebar = document.querySelector('.sidebar-container');

    function setCollapsed(collapsed){
        if (collapsed) { body.classList.add('sidebar-collapsed'); } else { body.classList.remove('sidebar-collapsed'); }
        try { localStorage.setItem('qc_sidebar_collapsed', collapsed? '1':'0'); } catch(e){}
    }

    // restore state
    try {
        const val = localStorage.getItem('qc_sidebar_collapsed');
        if (val === '1') setCollapsed(true);
    } catch(e){ }

    if (toggle) {
        toggle.addEventListener('click', function(e){ e.preventDefault(); const collapsed = document.body.classList.toggle('sidebar-collapsed'); setCollapsed(collapsed); });
    }
    if (miniToggle) {
        miniToggle.addEventListener('click', function(e){ e.preventDefault(); const collapsed = document.body.classList.toggle('sidebar-collapsed'); setCollapsed(collapsed); });
    }
    if (mobileClose) {
        mobileClose.addEventListener('click', function(){ if (sidebar) sidebar.classList.remove('show-mobile'); });
    }
    // make navbar brand button show sidebar on mobile
    if (toggle && sidebar) {
        // show on small screens when toggled
        toggle.addEventListener('click', function(){ if (window.innerWidth < 768) sidebar.classList.toggle('show-mobile'); });
    }
    const mobileOpen = document.getElementById('mobileSidebarOpen');
    if (mobileOpen && sidebar) {
        mobileOpen.addEventListener('click', function(e){ e.preventDefault(); sidebar.classList.add('show-mobile'); });
    }
})();
</script>

</body>
</html>

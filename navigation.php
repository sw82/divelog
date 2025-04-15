<nav class="menu">
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
    <div class="menu-items">
        <a href="index.php" id="nav-index">View Dive Log</a>
        <a href="populate_db.php" id="nav-populate">Manage Dives</a>
        <a href="fish_manager.php" id="nav-fish">Fish Species</a>
        <a href="manage_db.php" id="nav-db">Manage Database</a>
    </div>
</nav>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set active nav link based on current page
    const currentPath = window.location.pathname;
    const filename = currentPath.substring(currentPath.lastIndexOf('/')+1);
    
    // Remove all active classes first
    document.querySelectorAll('.menu a').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to current page link
    if (filename === 'index.php' || filename === '') {
        document.getElementById('nav-index').classList.add('active');
    } else if (filename === 'populate_db.php') {
        document.getElementById('nav-populate').classList.add('active');
    } else if (filename === 'fish_manager.php' || filename === 'fish_details.php') {
        document.getElementById('nav-fish').classList.add('active');
    } else if (filename === 'manage_db.php' || filename === 'backup_db.php' || filename === 'update_database.php' || 
               filename === 'import.php' || filename === 'process_ocr.php' || filename === 'save_ocr_data.php' || 
               filename === 'export_csv.php') {
        document.getElementById('nav-db').classList.add('active');
    }
    
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const menuItems = document.querySelector('.menu-items');
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            menuItems.classList.toggle('active');
        });
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (menuItems && menuItems.classList.contains('active') && 
            !event.target.closest('.menu') && event.target !== mobileMenuToggle) {
            menuItems.classList.remove('active');
        }
    });
});
</script> 
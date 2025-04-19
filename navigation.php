<nav class="menu">
    <div class="menu-toggle">
        <span class="hamburger-icon">☰</span>
    </div>
    <div class="menu-items">
        <a href="index.php" id="nav-index">Dive Map</a>
        <a href="divelist.php" id="nav-divelist">Divelist</a>
        <a href="fishlist.php" id="nav-fish">Fish List</a>
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
    } else if (filename === 'divelist.php') {
        document.getElementById('nav-divelist').classList.add('active');
    } else if (filename === 'fishlist.php' || filename === 'fish_details.php') {
        document.getElementById('nav-fish').classList.add('active');
    } else if (filename === 'fish_manager.php') {
        document.getElementById('nav-fish').classList.add('active');
    } else if (filename === 'populate_db.php' || filename === 'edit_dive.php' || filename === 'view_dive.php' || 
               filename === 'manage_db.php' || filename === 'backup_db.php' || filename === 'update_database.php' || 
               filename === 'import.php' || filename === 'process_ocr.php' || filename === 'save_ocr_data.php' || 
               filename === 'export_csv.php') {
        document.getElementById('nav-db').classList.add('active');
    }
    
    // Hamburger menu toggle functionality
    const menuToggle = document.querySelector('.menu-toggle');
    const menuItems = document.querySelector('.menu-items');
    
    menuToggle.addEventListener('click', function() {
        menuItems.classList.toggle('active');
    });
});
</script> 
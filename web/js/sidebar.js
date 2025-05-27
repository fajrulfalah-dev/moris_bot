$(document).ready(function() {
    // Toggle sidebar
    $('#toggleSidebar').click(function() {
        $('#sidebar').toggleClass('hidden');
        $('#content').toggleClass('expanded');
    });

    // Dropdown functionality
    $('.dropdown-btn').click(function(e) {
        e.stopPropagation();
        $(this).toggleClass('active');
        $(this).siblings('.dropdown-container').slideToggle(200);
        
        // Tutup dropdown lain yang terbuka
        $('.dropdown-btn').not(this).removeClass('active');
        $('.dropdown-container').not($(this).siblings('.dropdown-container')).slideUp(200);
    });

    // Tutup dropdown saat klik di luar
    $(document).click(function() {
        $('.dropdown-btn').removeClass('active');
        $('.dropdown-container').slideUp(200);
    });
});
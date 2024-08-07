jQuery(document).ready(function($) {
	jQuery(".gwslau-closebtn").click(function(){
		jQuery(".gwslau-logout-box-inner").css("display", "none");
		jQuery(".gwslau-logout-user-icon").css("display", "flex");
	});

	jQuery(".gwslau-logout-user-icon").click(function(){
		jQuery(".gwslau-logout-user-icon").css("display", "none");
		jQuery(".gwslau-logout-box-inner").css("display", "block");
	});
});
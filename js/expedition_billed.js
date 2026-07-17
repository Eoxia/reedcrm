$(document).ready(function() {
	$('.set-billed-js').on('click', function() {
		var $this = $(this);
		var expId = $this.data('id');
		var ajaxUrl = $this.data('url');
		var token = $this.data('token');
		
		$this.css('background-color', '#28a745').css('color', '#fff');
		
		$.ajax({
			url: ajaxUrl,
			method: 'POST',
			data: { id: expId, token: token },
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					$this.css('background-color', 'transparent').css('color', '');
					if (response.html) {
						$this.html(response.html);
					}
					// Removed class removal and event unbinding to allow toggling
				} else {
					alert('Erreur : ' + (response.error || 'Erreur inconnue'));
					$this.css('background-color', 'transparent').css('color', '');
				}
			},
			error: function() {
				alert('Erreur de communication AJAX.');
				$this.css('background-color', 'transparent').css('color', '');
			}
		});
	});
});

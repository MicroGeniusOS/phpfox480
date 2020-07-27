
$Core.registration = 
{
	iTotalSteps: 2,
	
	submitForm: function()
	{
	  var form = $('#js_form'),
      iStep = parseInt(form.data('step'));

		$('#core_js_messages').html('');
		$('#js_signup_error_message').html('');
		$('#js_register_accept').hide();		
		$('#js_registration_holder').hide();		
		$('#js_registration_process').css('height', $('#js_registration_holder').height() + 'px');
		$('#js_registration_process').show();
		
		form.ajaxCall('user.getRegistrationStep', 'step=' + iStep + '&last=' + (iStep == this.iTotalSteps ? '1' : '0') + '&next=' + ((iStep + 1) == this.iTotalSteps ? '1' : '0') + '');
	},
	
	updateForm: function(sHtml)
	{
    var form = $('#js_form'),
      iStep = parseInt(form.data('step'));

		$('#js_register_step' + iStep).hide();
		$('#js_signup_block').append(sHtml);
		$('#js_registration_process').hide();
		$('#js_registration_process').css('height', $('#js_registration_holder').height() + 'px');
		$('#js_registration_holder').show();

    form.data('step', ++iStep);
	},
	
	showCaptcha: function()
	{
		$('#js_register_capthca_image').show();	
	},

	useSuggested: function(oObj)
	{
		$('#user_name').val($(oObj).html());
		$('#js_verify_username').hide();
		$('#js_signup_user_name').html('<span style="color:green; font-weight:bold;">' + $(oObj).html() + '</span>');
	}
}

$Behavior.user_register_init = function()
{
	$('#js_submit_register_form').click(function()
	{	
		return $Core.registration.submitForm();
	});
};

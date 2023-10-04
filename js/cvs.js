(function ($) {
    Drupal.behaviors.cvsBehavior = {
      attach: function (context, settings) {
        $(document).ready(function(){
            if ($("#payment_methods :selected").length > 0 && 
            $('#payment_methods').val().includes('cvs')){
            $('#cvs_fieldset').show();
        }
        else $('#cvs_fieldset').hide();
        });
       
        $('#payment_methods').change(function() {
            if ($(this).val().includes('cvs')) {
                $('#cvs_fieldset').show('slow');
            }else{
                $('#cvs_fieldset').hide('slow');
            }
        });
      }
    };
  })(jQuery);
  
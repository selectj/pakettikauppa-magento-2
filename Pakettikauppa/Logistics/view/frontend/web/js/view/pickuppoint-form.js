/*global define*/
define([
    'Magento_Ui/js/form/form',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/shipping-rate-registry'
], function(Component, quote, shippingService, rateRegistry) {
    'use strict';
    return Component.extend({
        initialize: function () {
            this._super();
            // component initialization logic
            return this;
        },

        onSubmit: function() {
          if (jQuery('#pickuppoint-form input.input-text').length) {
            if (jQuery('#pickuppoint-form input.input-text').val().length) {

              this.source.set('params.invalid', false);
              this.source.trigger('pickuppointForm.data.validate');

              if (!this.source.get('params.invalid')) {
                  var formData = this.source.get('pickuppointForm');

                  jQuery.ajax({
                      showLoader: true,
                      url: '/logistics/index/index',
                      data: {zip_code:formData['pickuppoint-zip']},
                      type: "POST",
                      dataType: 'text',
                      success: function(data){

                        var address = quote.shippingAddress();
                        var shippingMethod = quote.shippingMethod();

                        address.trigger_reload = new Date().getTime();

                        rateRegistry.set(address.getKey(), null);
                        rateRegistry.set(address.getCacheKey(), null);

                        quote.shippingAddress(address);
                      },
                      error : function(request,error){
                        console.dir("Error");
                      }
                  })
              }
            }
        }
      }
    });
});

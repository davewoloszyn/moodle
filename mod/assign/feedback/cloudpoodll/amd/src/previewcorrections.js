define(['jquery', 'core/log','core/str','core/ajax','core/notification','assignfeedback_cloudpoodll/correctionsmarkup'],
    function ($, log, str, Ajax, notification,correctionsmarkup) {
    "use strict"; // jshint ;_;
    /*
    This file does small report
     */

    log.debug('Preview corrections: initialising');
    return {
        //controls
        controls: {},
        previewtimer: null,

        //init the module
        init: function(){
            this.init_strings();
            this.register_controls();
            this.register_events();
            //on first load do render. If we have are loading an existing feedback we should preview the correctons
            this.render_and_markup();
        },

        init_strings: function(){
          var that =this;
          //str.get_string('checking','assignfeedback_cloudpoodll').done(function(s){that.checking=s;});
        },

        //load all the controls so we do not have to do it later
        register_controls: function(){
            this.controls.textareas = $('#id_submittedtext, #id_correctedtext');
            this.controls.submittedtextarea = $('#id_submittedtext');
            this.controls.correctionstextarea = $('#id_correctedtext');
            this.controls.correctionscontainer = $('.asf_cp_corrections_cont');
            this.controls.previewcontainer = $('.asf_cp_correctionspreview_cont');
            this.controls.animcontainer = $('#asf_cp_correctionspreview_anim');
        },

        //attach the various event handlers we need
        register_events: function() {
            var that = this;

            //if either text area changes start the timer to do a preview
            that.controls.textareas.on('input', function() {
                clearTimeout(that.previewtimer);   // clear the timer whenever the input is changed
                that.previewtimer = setTimeout(function(
                ) {  // after 2.5s, log something to the console
                    that.render_and_markup();
                }, 2500);
            });

        },//end of register events

        render_and_markup: function () {
            var that = this;
            var thepassage = that.controls.submittedtextarea.val();
            var thecorrections = that.controls.correctionstextarea.val();
            //if no passage or corrections do nothing
            if(thepassage.length<1 || thecorrections.length<1){
                return;
            }

            //show the spinner animation
            that.controls.animcontainer.show();

            Ajax.call([{
                methodname: 'assignfeedback_cloudpoodll_render_diffs',
                args: {
                    passage: thepassage,
                    corrections: thecorrections,
                },
                done: function (ajaxresult) {
                    that.controls.animcontainer.hide(); //hide the spinner
                    var payloadobject = JSON.parse(ajaxresult);
                    if (payloadobject) {
                        if(payloadobject.markedupsuggestions.length>3){
                            that.controls.correctionscontainer.html(payloadobject.markedupsuggestions);
                            correctionsmarkup.justmarkup( that.controls.correctionscontainer,
                                payloadobject.grammarerrors,
                                payloadobject.grammarmatches,
                                payloadobject.insertioncount);
                            //initially the preview container is hidden
                            that.controls.previewcontainer.show();
                        }
                    }else{
                        //something went wrong, user does not really need to know details
                        log.debug('result not fetched');
                    }

                },
                fail: notification.exception
            }]);
        },

    };//end of return value
});
define(['jquery', 'core/log','core/str','core/ajax','core/notification','core/modal_factory', 'core/modal_events'],
    function ($, log, str, Ajax, notification, ModalFactory, ModalEvents) {
    "use strict"; // jshint ;_;
    /*
    This file does small report
     */

    log.debug('Fetch Submission: initialising');

    return {
        //controls
        controls: {},
        overwrite: 'OVERWRITE',
        overwritefetchedsubmission: 'Overwrite the submitted text?',
        overwritewarning: 'WARNING: Overwrite',

        //init the module
        init: function () {
            this.register_controls();
            this.init_strings();
            this.register_events();
        },

        init_strings: function(){
            var that =this;
            str.get_string('overwrite','assignfeedback_cloudpoodll').done(function(s){that.overwrite=s;});
            str.get_string('overwritefetchedsubmission','assignfeedback_cloudpoodll').done(function(s){that.overwritefetchedsubmission=s;});
            str.get_string('overwritewarning','assignfeedback_cloudpoodll').done(function(s){that.overwritewarning=s;});

        },

        //load all the controls so we do not have to do it later
        register_controls: function () {
            this.controls.fetchsubmissionbutton = $('.fetchsubmissiontrigger');
        },

        //attach the various event handlers we need
        register_events: function () {
            var that = this;
            that.controls.fetchsubmissionbutton.click(function(e){
                //collect target element from  data-target
                var targetselector  = $(this).data('target');

                //if we already have grammar suggestions, we need to ask if we should overwrite
                if($(targetselector).val().length>0) {
                    ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: that.overwritewarning,
                        body: that.overwritefetchedsubmission,
                    })
                        .then(function (themodal) {
                            themodal.setSaveButtonText(that.overwrite);
                            var root = themodal.getRoot();
                            root.on(ModalEvents.save,
                                function (e) {
                                    that.do_fetch(targetselector);
                                }
                            );
                            themodal.show();
                            return themodal;
                        });
                }else {
                    //if not we just fetch grammar suggestions
                    that.do_fetch(targetselector);
                }
                return false;
            });
        },//end of register events

        do_fetch: function(targetselector){
            //fetch submitted text first from cloud poodll
            var submittedtext ='';
            var cp_transcript = $('.assignsubmission_cloudpoodll_transcript-text');
            if (cp_transcript.length > 0) {
                submittedtext=cp_transcript.text();
            }else{
                //if no cloud poodll lets try online text
                var ot_summary = $('.assignsubmission_onlinetext .plugincontentsummary');
                if(ot_summary.length > 0){
                    //ok we have an online text, but we have to work quite hard to get the text
                    var lastclass = ot_summary.attr('class').split(' ').pop();
                    var fulltextclass = lastclass.replace('summary', 'full');
                    var ot_transcript=$('.' + fulltextclass);
                    if(ot_transcript.length > 0){
                        submittedtext=ot_transcript.text();
                    }
                }
            }
            if (submittedtext !== '') {
                $(targetselector).val(submittedtext);
            }
        }
    };

});
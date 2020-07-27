var $sFormAjaxRequest = null;
var $bButtonSubmitActive = true;
var $ActivityFeedCompleted = {};
var $sCssHeight = '40px';
var $sCustomPhrase = null;
var $sCurrentForm = null;
var $sStatusUpdateValue = null;
var $iReloadIteration = 0;
var $iPageLoadMore = 1;
var $oLastFormSubmit = null;
var bCheckUrlCheck = false;
var bCheckUrlForceAdd = false;
var bAddingFeed = false;
var $sCacheFeedErrorMessage = [];

$Core.Like = {};
$Core.Like.Actions = {
  doLike: function (bIsCustom, sItemTypeId, iItemId, iParentId, oObj) {
    if ($(oObj).closest('.comment_mini_link_like').find('.like_action_unmarked').is(':visible')) {
      $(oObj).closest('.comment_mini_link_like').find('.like_action_marked').show();
      $(oObj).closest('.comment_mini_link_like').find('.like_action_unmarked').hide();
    }
    $(oObj).parent().find('.js_like_link_unlike:first').show();
    $(oObj).hide();
    $.ajaxCall('like.add', 'type_id=' + sItemTypeId + '&item_id=' + iItemId +
      '&parent_id=' + iParentId + '&custom_inline=' + bIsCustom, 'GET');
  },
};

$Core.isInView = function (elem, item) {
  if (!$Core.exists(elem)) {
    return false;
  }

  var docViewTop = $(window).scrollTop();
  var docViewBottom = docViewTop + $(window).height();

  var elemTop = $(elem).offset().top;
  var elemBottom = (elemTop + $(elem).height());
  if (item) {
    elemBottom = (elemBottom - parseInt(item));
  }

  return ((docViewTop < elemTop) && (docViewBottom > elemBottom));
};

$Core.resetActivityFeedForm = function () {
  if ($sCacheFeedErrorMessage.length > 0) {
    $('.activity_feed_form_share_process').hide();
    $('.activity_feed_form_button .button').removeClass('button_not_active');
    $('#activity_feed_upload_error').empty();
    $bButtonSubmitActive = true;
    $sCacheFeedErrorMessage.forEach(function (item, index) {
      $('#activity_feed_upload_error').append('<div class="error_message">' + item + '</div>').show();
    });

    $sCacheFeedErrorMessage = [];

    PF.event.trigger('on_show_cache_feed_error_message');
  } else {
    bAddingFeed = false;
    $('._load_is_feed').removeClass('active');
    $('#panel').hide();
    $('body').removeClass('panel_is_active');

    $('.activity_feed_form_attach li a').removeClass('active');
    $('.activity_feed_form_attach li:not(.share) a:first').addClass('active');
    $('.global_attachment_holder_section').hide();
    $('#global_attachment_status').show();
    $('.global_attachment_holder_section textarea').val('').css({height: $sCssHeight});

    $('.activity_feed_form_button_status_info').hide();
    $('.activity_feed_form_button_status_info textarea').val('');

    $Core.resetActivityFeedErrorMessage();

    $sFormAjaxRequest = $('.activity_feed_form_attach li a.active').find('.activity_feed_link_form_ajax').html();

    $Core.activityFeedProcess(false);

    $('.js_share_connection').val('0');
    $('.feed_share_on_item a').removeClass('active');

    $.each($ActivityFeedCompleted, function () {
      this(this);
    });

    $('#js_add_location, #js_location_input, .js_location_feedback').removeClass('hide active').hide();

    if (typeof $Core.FeedTag !== 'undefined' &&
      typeof $Core.FeedTag.iFeedId !== 'undefined' &&
      $('#feed_input_tagged_' + $Core.FeedTag.iFeedId).length) {
      $('#feed_input_tagged_' + $Core.FeedTag.iFeedId).val('');
      $('.js_feed_tagged_items').html('');
      $('.js_tagged_review').html('').hide().removeClass('tagged_review');
      $('.js_feed_compose_tagging').hide();
      $('.js_btn_display_with_friend').removeClass('is_active');
    }
    //remove e-gift after submit
    if ($('#js_core_egift_id').length) {
      $('#js_core_egift_id').val('');
    }
    $('.activity_feed_form_button_position').show();
    $('#hdn_location_name, #val_location_name ,#val_location_latlng, #video_url').val('');
    $('.js_location_feedback').html('');
    $('#btn_display_check_in').removeClass('is_active');

    // remove all photos selected
    if ($('.dz-remove-file').length) {
      $('.dz-remove-file').trigger('click');
    }
  }

  // reset Share button
  $('#activity_feed_submit').removeClass('button_not_active').attr('disabled', false);
};

$(document).on('click', '.dz-remove-file', function () {

});
$Core.resetActivityFeedErrorMessage = function () {
  $('#activity_feed_upload_error').hide();
  $('#activity_feed_upload_error_message').html('');
};

$Core.resetActivityFeedError = function (sMsg) {
  $('.activity_feed_form_share_process').hide();
  $('.activity_feed_form_button .button').removeClass('button_not_active');
  $bButtonSubmitActive = true;
  $('#activity_feed_upload_error').html('<div class="alert alert-danger">' + sMsg + '</div>').show();
};

$Core.cacheActivityFeedError = function (sMsg) {
  $sCacheFeedErrorMessage.push(sMsg);
};

$Core.clearActivityFeedError = function () {
  $sCacheFeedErrorMessage = [];
};

$Core.activityFeedProcess = function ($bShow) {
  var parent = $('#js_activity_feed_edit_form').length ? $('#js_activity_feed_edit_form') : $('#js_activity_feed_form');
  var processEdit = $('#js_activity_feed_edit_form').length ? true : false;
  if ($bShow) {
    $bButtonSubmitActive = false;
    $('.activity_feed_form_share_process').show();
    if (processEdit) {
      if ($('.activity_feed_form_button .button', parent).length) {
        $('.activity_feed_form_button .button', parent).addClass('button_not_active');
      } else {
        $('.activity_feed_form_button input[type="submit"]', parent).addClass('button_not_active');
      }
    } else {
      $('.activity_feed_form_button .button', parent).addClass('button_not_active');
    }
  } else {
    $bButtonSubmitActive = true;
    $('.activity_feed_form_share_process').hide();
    if (processEdit) {
      if ($('.activity_feed_form_button .button', parent).length) {
        $('.activity_feed_form_button .button', parent).removeClass('button_not_active');
      } else {
        $('.activity_feed_form_button input[type="submit"]', parent).removeClass('button_not_active');
      }
    } else {
      $('.activity_feed_form_button .button', parent).removeClass('button_not_active');
    }
    $('.egift_wrapper').hide();
    if ($('.egift_wrapper').length > 0 && $('#egift_id')) {
      $('.egift_item').removeClass('eGiftHighlight');
      $('#egift_id').val('');
      $('.egift_wrapper').show();
    }
  }
};

$Core.addNewPollOption = function (iMaxAnswers, sLimitReached) {
  if (iMaxAnswers >= ($('#js_poll_feed_answer li').length + 1)) {
    $('.js_poll_feed_answer').append(
      '<li><input type="text" name="val[answer][][answer]" value="" size="30" class="js_feed_poll_answer v_middle" /></li>');
  } else {
    alert(oTranslations['you_have_reached_your_limit']);
  }

  return false;
};

$Core.forceLoadOnFeed = function () {
  if ($iReloadIteration > 0 || $iPageLoadMore > oParams.iLimitLoadMore) {
    return;
  }

  if (!$Core.exists('#js_feed_pass_info')) {
    return;
  }

  $iReloadIteration++;
  $iPageLoadMore++;
  $('#feed_view_more_loader').show();
  $('.global_view_more').remove();

  setTimeout(function () {
    $Core.loadMoreFeed($iReloadIteration);
  }, 1000);
};

$Core.loadMoreFeed = function (iReloadIteration) {
  var oLastFeed = $('.js_parent_feed_entry').last(),
    iLastFeedId = (oLastFeed) ? oLastFeed.data('feed-id') : null,
    sForceFlavor = (oLastFeed && oLastFeed.data('force-flavor')) ? '&force-flavor=' + oLastFeed.attr('data-force-flavor') : '';

  // ajax call to get new feeds
  $.ajaxCall('feed.viewMore', $('#js_feed_pass_info').html().replace(/&amp;/g, '&') + '&iteration="' + iReloadIteration + '"&last-feed-id=' + iLastFeedId + sForceFlavor, 'GET');
};

var postingFeedUrl = false;
var checkMatch = [];

$Core.handlePasteInFeed = function (oObj) {
  if (postingFeedUrl) {
    return false;
  }
  var value = $(oObj).val();
  var regrex = /(http[s]?:\/\/(www\.)?|ftp:\/\/(www\.)?|www\.){1}([0-9A-Za-z-\-\.@:%_\+~#=]+)+((\.[a-zA-Z])*)(\/([0-9A-Za-z-\-\.@:%_\+~#=\?])*)*/g;
  var match = value.match(regrex);
  if (!empty(match)) {
    setTimeout(function () {
      var newValue = $(oObj).val();
      var newMatch = newValue.match(regrex);
      if (empty(newMatch) || (match[0] != newMatch[0]) || checkMatch[0] == newMatch[0]) {
        return false;
      }
      checkMatch = newMatch;
      bCheckUrlCheck = true;
      postingFeedUrl = true;

      $('#activity_feed_submit').attr('disabled', 'disabled');

      $('.activity_feed_form_share_process').show();
      $(oObj).parent().append('<div id="js_preview_link_attachment_custom_form_sub" class="js_preview_link_attachment_custom_form mt-1"></div>');
      $Core.ajax('link.preview', {
        type: 'POST',
        params: {
          'no_page_update': '1',
          value: newMatch[0],
        },
        success: function ($sOutput) {
          postingFeedUrl = false;
          checkMatch = [];
          $('.activity_feed_form_share_process').hide();
          if (substr($sOutput, 0, 1) == '{') {
            var output = JSON.parse($sOutput);

            if (typeof output.error !== 'undefined') {
              $('#activity_feed_submit').attr('disabled', false);
            }
          } else {
            $('#js_global_attach_value').val($(oObj).val());
            bCheckUrlForceAdd = true;
            $('#js_preview_link_attachment_custom_form_sub').html($sOutput);
          }
        },
      });
    }, 500);
  } else {
    $('#js_global_attach_value').val('');
    bCheckUrlForceAdd = false;
    checkMatch = [];
    $('#js_preview_link_attachment_custom_form_sub').remove();
  }
  $Core.resizeTextarea($(oObj));
};

/**
 * Editor on comments
 */
$Core.loadCommentButton = function () {
  $('.feed_comment_buttons_wrap div input.button_set_off').show().removeClass('button_set_off');
};

var __ = function (e) {
  $('.feed_stream[data-feed-url="' + e.url + '"]').replaceWith(e.content);
  $Core.loadInit();
};

$Core.resetFeedForm = function (f) {
  f.get()[0].reset();
  $('.feed_form_share').removeClass('.active');
  $('.feed_form_textarea textarea').removeClass('dont-unbind');
};

window.onerror = function (e) {
};

var load_feed_entries = false;
var load_feed_action = function () {
  var total = $('.feed_stream:not(.built)').length, iteration = 0;
  $('.feed_stream:not(.built)').each(function () {
    var t = $(this);

    t.addClass('built');
    iteration++;
    if (iteration === 2) {
      return false;
    }

    var s = document.createElement('script');
    s.type = 'application/javascript';
    s.src = t.data('feed-url');
    document.head.appendChild(s);

    // return false;
  });
};

$(document).on('click', '[data-component="feed-comment-view-more"]', function () {
  var commentWrapper = $(this).closest('.feed_comments_wrapper');

  $('.js_mini_feed_comment', commentWrapper).removeClass('hidden');
  $('.comment_pager_holder', commentWrapper).show();
  $(this).remove();
});

$Behavior.activityFeedProcess = function () {
  $('.comment-limit:not(.is_checked)').each(function () {
    var t = $(this);
    t.addClass('is_checked');
    var total = t.children('.js_mini_feed_comment').length,
      limit = t.data('limit'),
      iteration = total,
      totalHidden = 0,
      comments = t.children('.js_mini_feed_comment');

    comments.each(function () {
      var l = $(this);
      iteration--;
      if (iteration < limit) {
        return false;
      }

      totalHidden++;
      l.addClass('hidden');
    });

    if (totalHidden) {
      var cHolder = t.parent().find('.comment_pager_holder:first');
      cHolder.hide();

      var viewMore = $('<a role="button" class="load_more_comments dont-unbind" data-component="feed-comment-view-more">' + oTranslations['view_previous_comments'] + '</a>');

      cHolder.before(viewMore);
    }
  });

  $('.comment_mini_link_like_empty').each(function () {
    var p = $(this).closest('.comment_mini_content_border');
  });

  if (!$Core.exists('#js_feed_content')) {
  }

  if ($Core.exists('.global_view_more') && ($(window).width() > 480)) {
    if ($Core.isInView('.global_view_more')) {
      $Core.forceLoadOnFeed();
    }

    window.handleFeedViewMoreScroll && $(window).off('scroll', handleFeedViewMoreScroll);
    handleFeedViewMoreScroll = function () {
      if ($Core.isInView('.global_view_more')) {
        $Core.forceLoadOnFeed();
      }
    };
    $(window).on('scroll', handleFeedViewMoreScroll);
  }

  $('.like_count_link').each(function () {
    var sHtml = $(this).parent().find('.like_count_link_holder:first').html();
  });

  $sFormAjaxRequest = $('.activity_feed_form_attach li a.active').find('.activity_feed_link_form_ajax').html();
  if (typeof Plugin_sFormAjaxRequest == 'function') {
    Plugin_sFormAjaxRequest();
  }

  if ($Core.exists('.profile_timeline_header')) {
    $(window).scroll(function () {
      if (isScrolledIntoView('.profile_timeline_header')) {
        $('.timeline_main_menu').removeClass('timeline_main_menu_fixed');
        $('#timeline_dates').removeClass('timeline_dates_fixed');
      } else {
        if (!$('.timeline_main_menu').hasClass('timeline_main_menu_fixed')) {
          $('.timeline_main_menu').addClass('timeline_main_menu_fixed');

          if ($('#content').height() > 600) {
            $('#timeline_dates').addClass('timeline_dates_fixed');
          }
        }
      }
    });
  }

  $('#global_attachment_status textarea,.activity_feed_form_button_status_info textarea').keyup(function () {
    if ($sCurrentForm === null || $sCurrentForm === 'global_attachment_status') {
      $Core.handlePasteInFeed($(this));
    }
  }).bind('paste', function () {
    var that = this;
    if ($sCurrentForm === null || $sCurrentForm === 'global_attachment_status') {
      setTimeout(function () {
        $Core.handlePasteInFeed(that);
      }, 0);
    }
  });

  $('#global_attachment_status textarea').keydown(function () {
    $Core.resizeTextarea($(this));
  });

  $('.activity_feed_form_button_status_info textarea').keydown(function () {
    $Core.resizeTextarea($(this));
  });

  $('#global_attachment_status textarea').focus(function () {
    var t = $(this);
    if (t.hasClass('_is_set')) {
      return;
    }
    t.addClass('_is_set');
    $('.activity_feed_form_button').show();
    $(this).addClass('focus');
    $('.activity_feed_form_button_status_info textarea').addClass('focus');
  });

  $('.activity_feed_form_button_status_info textarea').focus(function () {
    var $sDefaultValue = $(this).val();
    var $bIsDefault = true;

    $('.activity_feed_extra_info').each(function () {
      if ($(this).html() == $sDefaultValue) {
        $bIsDefault = false;

        return false;
      }
    });

    if (($('#global_attachment_status textarea').val() ==
      $('#global_attachment_status_value').html() &&
      empty($sDefaultValue)) || !$bIsDefault) {
      $(this).css({height: '50px'});
      $(this).addClass('focus');
      $('#global_attachment_status textarea').addClass('focus');
    }
  });

  $('#js_activity_feed_form, #js_activity_feed_edit_form').submit(function () {
    if ($sCurrentForm === 'global_attachment_status') {
      var oStatusUpdateTextareaFilled = $('#global_attachment_status textarea');
      if ($sStatusUpdateValue == oStatusUpdateTextareaFilled.val()) {
      }
    } else {
      var oCustomTextareaFilled = $(
        '.activity_feed_form_button_status_info textarea');

      if ($sCustomPhrase == oCustomTextareaFilled.val()) {
        oCustomTextareaFilled.val('');
      }
    }

    if ($bButtonSubmitActive === false) {
      return false;
    }

    $Core.activityFeedProcess(true);
    if (typeof $sFormAjaxRequest === 'undefined' ||
      $sFormAjaxRequest === null) {
      return true;
    }

    $('.js_no_feed_to_show').remove();

    var sExtra = '';
    if (bCheckUrlForceAdd) {
      $('.activity_feed_form_button_status_info textarea').val($('#global_attachment_status textarea').val());
      $sFormAjaxRequest = 'link.addViaStatusUpdate';
      if ($('#js_activity_feed_edit_form').length > 0) {
        sExtra = 'force_form=1';
      }
    }
    bAddingFeed = true;

    if ($('#js_activity_feed_edit_form').length) {
      var editForm = $('#js_activity_feed_edit_form');
      if (editForm.find('#custom_ajax_form_submit').length && editForm.find('#js_preview_link_attachment_custom_form_sub').length) {
        editForm.find('#custom_ajax_form_submit').html('link.addViaStatusUpdate');
      }
    }

    var customAjax = $('#custom_ajax_form_submit');
    if (customAjax.length) {
      $(this).ajaxCall(customAjax.html(), sExtra);
    } else {
      $(this).ajaxCall($sFormAjaxRequest, sExtra);
    }

    if (bCheckUrlForceAdd) {
      $('#js_preview_link_attachment_custom_form_sub').remove();
      bCheckUrlForceAdd = false;
    }

    return false;
  });

  $('.activity_feed_form_attach li a').each(function () {
    $(this).click(function () {
      $sCurrentForm = $(this).attr('rel');
      $('.js_btn_display_with_friend').show();

      if ($sCurrentForm === 'custom') {
        if ($('#video_url').length == 0 || $('#video_url').val() == "") {
          $('#activity_feed_submit').addClass('button_not_active');
        }
        if ($('.js_tagged_review').html() != "" || $('.activity_feed_form_button .js_location_feedback').html() != "" || ($('#video_url').length && $('#video_url').val() != "")) {
          $('.activity_feed_form_button_status_info').show();
        }
        if ($('#video_url').val() != "") {
          $Core.Video.addShareVideoBtnInFeed();
        }
        $('.core-egift-wrapper').hide();
        $('.activity_feed_form_holder .js_tagged_review').addClass('hide');
        $('.activity_feed_form_holder .js_location_feedback').addClass('hide');
        return false;
      }

      if ($sCurrentForm === 'view_more_link') {
        $('.core-egift-wrapper').hide();
        $('.view_more_drop').toggle();

        return false;
      } else {
        $('.view_more_drop').hide();
      }

      if ($sCurrentForm === 'global_attachment_photo') {
        $('.activity_feed_form_holder .js_tagged_review').addClass('hide');
        $('.activity_feed_form_holder .js_location_feedback').addClass('hide');
      } else {
        $('.activity_feed_form_holder .js_tagged_review').removeClass('hide');
        $('.activity_feed_form_holder .js_location_feedback').removeClass('hide');
      }

      $('.activity_feed_form_button_position').show();
      if ($sCurrentForm === 'global_attachment_status') {
        $('#global_attachment_status textarea[name="val[user_status]"]').addClass('focus');
      }

      $('#js_preview_link_attachment_custom_form_sub').remove();
      $('#activity_feed_upload_error').hide();

      $('.global_attachment_holder_section').hide();
      $('.activity_feed_form_attach li a').removeClass('active');
      $(this).addClass('active');

      if ($(this).find('.activity_feed_link_form').length > 0) {
        $('#js_activity_feed_form').attr('action', $(this).find('.activity_feed_link_form').html()).attr('target', 'js_activity_feed_iframe_loader');
        $sFormAjaxRequest = null;
        if (empty($('.activity_feed_form_iframe').html())) {
          $('.activity_feed_form_iframe').html(
            '<iframe id="js_activity_feed_iframe_loader" name="js_activity_feed_iframe_loader" height="200" width="500" frameborder="1" style="display:none;"></iframe>');
        }
      } else {
        $sFormAjaxRequest = $(this).find('.activity_feed_link_form_ajax').html();
      }

      $('#' + $(this).attr('rel')).show();
      $('.activity_feed_form_holder_attach').show();
      $('.activity_feed_form_button').show();

      var $oStatusUpdateTextarea = $('#global_attachment_status textarea');
      var $sStatusUpdateTextarea = $oStatusUpdateTextarea.val();
      $sStatusUpdateValue = $('#global_attachment_status_value').html();

      var $oCustomTextarea = $('.activity_feed_form_button_status_info textarea');
      var $sCustomTextarea = $oCustomTextarea.val();

      $sCustomPhrase = $(this).find('.activity_feed_extra_info').html();

      var $bHasDefaultValue = false;
      $('.activity_feed_extra_info').each(function () {
        if ($(this).html() == $sCustomTextarea) {
          $bHasDefaultValue = true;

          return false;
        }
      });

      if ($sCurrentForm !== 'global_attachment_status') {
        $('.activity_feed_form_button_status_info').show();
        $('.core-egift-wrapper').hide();
        if ((empty($sCustomTextarea) &&
          ($sStatusUpdateTextarea == $sStatusUpdateValue
            || empty($sStatusUpdateTextarea)))
          ||
          ($sStatusUpdateTextarea == $sStatusUpdateValue && $bHasDefaultValue)
          || (!$bButtonSubmitActive && $bHasDefaultValue)
        ) {
          $oCustomTextarea.css({height: $sCssHeight}).val('').prop({placeholder: $sCustomPhrase});
        } else if ($sStatusUpdateTextarea != $sStatusUpdateValue &&
          $bButtonSubmitActive && !empty($sStatusUpdateTextarea)) {
          $oCustomTextarea.val($sStatusUpdateTextarea);
        }

        if ($sCurrentForm === 'global_attachment_photo' && $('#global_attachment_photo .dz-image-preview').length && !$('#global_attachment_photo .dz-image-preview.dz-error').length) {
          $('.activity_feed_form_button .button').removeClass('button_not_active');
          $bButtonSubmitActive = true;
        } else {
          $('.activity_feed_form_button .button').addClass('button_not_active');
          $bButtonSubmitActive = false;
        }
      } else {
        $('.core-egift-wrapper').show();
        $('.activity_feed_form_button_status_info').hide();
        $('.activity_feed_form_button .button').removeClass('button_not_active');

        if (!$bHasDefaultValue && !empty($sCustomTextarea)) {
          $oStatusUpdateTextarea.val($sCustomTextarea);
        } else if ($bHasDefaultValue && empty($sStatusUpdateTextarea)) {
          $oStatusUpdateTextarea.val($sStatusUpdateValue).css({height: $sCssHeight});
        }
        $Core.handlePasteInFeed($oStatusUpdateTextarea);
        $bButtonSubmitActive = true;
      }

      if ($(this).hasClass('no_text_input')) {
        $('.activity_feed_form_button_status_info').hide();
      }

      $('.activity_feed_form_button .button').show();
      $('#js_piccup_upload').hide();

      return false;
    }).prop('built', true);
  });
};

PF.event.on('on_page_column_init_end', function () {
  $sCurrentForm = null;
});


$Behavior.activityFeedLoader = function () {
  if (empty($('.view_more_drop').html())) {
    $('.timeline_view_more').parent().hide();
  }

  /**
   * Click on adding a new comment link.
   */
  $('.js_feed_entry_add_comment').click(function () {
    $('.js_comment_feed_textarea').each(function () {
      if ($(this).val() == $('.js_comment_feed_value').html()) {
        $(this).removeClass('js_comment_feed_textarea_focus');
        $(this).val($('.js_comment_feed_value').html());
      }

      $(this).parents('.comment_mini').find('.feed_comment_buttons_wrap').hide();
    });

    $(this).parents('.js_parent_feed_entry:first').find('.comment_mini_content_holder').show();
    $(this).parents('.js_parent_feed_entry:first').find('.feed_comment_buttons_wrap').show();

    if ($(this).parents('.js_parent_feed_entry:first').find('.js_comment_feed_textarea').val() == $('.js_comment_feed_value').html()) {
      $(this).parents('.js_parent_feed_entry:first').find('.js_comment_feed_textarea').val('');
    }
    $(this).parents('.js_parent_feed_entry:first').find('.js_comment_feed_textarea').focus().addClass('js_comment_feed_textarea_focus');
    $(this).parents('.js_parent_feed_entry:first').find('.comment_mini_textarea_holder').addClass('comment_mini_content');

    var iTotalComments = 0;
    $(this).parents('.js_parent_feed_entry:first').find('.js_mini_feed_comment').each(function () {
      iTotalComments++;
    });

    if (iTotalComments > 2) {
      $.scrollTo($(this).parents('.js_parent_feed_entry:first').find('.js_comment_feed_textarea_browse:first'), 340);
    }

    return false;
  });

  /**
   * Comment textarea on focus.
   */
  $('.js_comment_feed_textarea').focus(function () {
    $Core.commentFeedTextareaClick(this);
  });

  $('#js_captcha_load_for_check_submit').submit(function () {
    if (function_exists('' + Editor.sEditor + '_wysiwyg_feed_comment_form')) {
      eval('' + Editor.sEditor + '_wysiwyg_feed_comment_form(this);');
    }

    $oLastFormSubmit.parent().parent().find('.js_feed_comment_process_form:first').show();
    $(this).ajaxCall('comment.add', $oLastFormSubmit.getForm());
    isAddingComment = false;
    return false;
  });

  $('.js_comment_feed_form').unbind().submit(function () {
    var t = $(this);
    t.addClass('in_process');
    if ($Core.exists('#js_captcha_load_for_check')) {
      $('div#js_captcha_load_for_check').removeClass('built');
      var captchaCheckEle = $('#js_captcha_load_for_check');
      captchaCheckEle.addClass('built').css({
        top: t.offset().top,
        left: '50%',
        'margin-left': '-' +
          ((captchaCheckEle.width() / 2) + 12) + 'px',
        display: 'block',
      }).detach().appendTo('body');
      $oLastFormSubmit = $(this);
      $('div#js_captcha_load_for_check:not(.built)').remove();

      var captchaType = captchaCheckEle.data('type');
      if (captchaType == 'recaptcha') {
        // reset token
        var reCaptchaEle = captchaCheckEle.find('#g-recaptcha');
        if (reCaptchaEle.length && reCaptchaEle.data('type') == 3) {
          $Core.captcha.addRecaptchaToken(reCaptchaEle.data('sitekey'));
        } else if (typeof grecaptcha !== 'undefined') {
          grecaptcha.reset();
        }
      } else {
        // reload image
        captchaCheckEle.find('.captcha').attr('id', 'js_captcha_image').css({opacity: 0.0});
        $('#js_captcha_image').ajaxCall('captcha.reload', 'sId=js_captcha_image&sInput=image_verification');
      }
      return false;
    }

    if (function_exists('' + Editor.sEditor + '_wysiwyg_feed_comment_form')) {
      eval('' + Editor.sEditor + '_wysiwyg_feed_comment_form(this);');
    }

    $(this).parent().parent().find('.js_feed_comment_process_form:first').show();
    $(this).ajaxCall('comment.add', null, null, null, function (e, self) {
      $(self).find('textarea').blur();
      isAddingComment = false;
      $('.js_feed_comment_process_form').fadeOut();
    });

    $(this).find('.error_message').remove();
    $(this).find('textarea:first').removeClass('dont-unbind');

    return false;
  });
};

$(document).on('click', '.js_comment_feed_new_reply', function () {
  var commentMini = $(this).closest('.comment_mini');

  if (commentMini.length) {
    var parentComment = commentMini.parent().closest('.comment_mini');
    if (parentComment.length) {
      $('.js_comment_feed_new_reply', parentComment).first().trigger('click');

      return;
    }
  }

  var oParent = $(this).parents('.js_mini_feed_comment:first').children('.js_comment_form_holder:first');
  var oGrand = oParent.parent();
  oParent.detach().appendTo(oGrand);

  if ((Editor.sEditor == 'tiny_mce' || Editor.sEditor == 'tinymce') &&
    isset(tinyMCE) && isset(tinyMCE.activeEditor)) {
    $('.js_comment_feed_form').find('.js_feed_comment_parent_id:first').val($(this).attr('rel'));
    tinyMCE.activeEditor.focus();
    if (typeof ($.scrollTo) == 'function') {
      $.scrollTo('.js_comment_feed_form', 800);
    }
    return false;
  }

  var sCommentForm = $(this).parents('.js_feed_comment_border:first').find('.js_feed_comment_form:first').html();

  oParent.html(sCommentForm);
  oParent.find('.js_feed_comment_parent_id:first').val($(this).attr('rel'));

  var textarea = $('textarea', oParent);
  textarea.focus().attr('placeholder', oTranslations['write_a_reply']);
  $Core.commentFeedTextareaClick(textarea);
  $Core.resizeTextarea(textarea);
  textarea.val('');

  $('.js_feed_add_comment_button .error_message').remove();

  oParent.find('.button_set_off:first').show().removeClass('button_set_off');
  oParent.closest('.js_mini_feed_comment').addClass('has-replies');

  $Core.loadInit();
  return false;
});

var isAddingComment = false;
$Core.commentFeedTextareaClick = function ($oObj) {
  $($oObj).addClass('dont-unbind');
  $($oObj).blur(function () {
    $(this).removeClass('dont-unbind');
  });
  $($oObj).keydown(function (e) {
    if (isAddingComment) {
      return false;
    }

    if (e.which == 13) {
      setTimeout(function () {
        $('.chooseFriend').remove();
      }, 100);
      if (e.ctrlKey || e.metaKey) {
        var val = this.value;
        var start = this.selectionStart;
        this.value = val.slice(0, start) + "\n" + val.slice(this.selectionEnd);
        this.selectionStart = this.selectionEnd = start + 1;
      } else {
        e.preventDefault();
        $($oObj).parents('form:first').trigger('submit');
        $($oObj).removeClass('dont-unbind');
        $Core.loadInit();
        isAddingComment = true;
        return false;
      }
    }
  }).keyup(function () {
    $Core.resizeTextarea($(this));
  }).on('paste', function () {
    $Core.resizeTextarea($(this));
  });

  $($oObj).addClass('js_comment_feed_textarea_focus').addClass('is_focus');
  $($oObj).parents('.comment_mini').find('.feed_comment_buttons_wrap:first').show();

  $($oObj).parent().parent().find('.comment_mini_textarea_holder:first').addClass('comment_mini_content');
};

$Behavior.activityFeedAttachLink = function () {
  $('#js_global_attach_link').click(function () {
    $Core.activityFeedProcess(true);

    $Core.ajax('link.preview',
      {
        params:
          {
            'no_page_update': '1',
            value: $('#js_global_attach_value').val(),
          },
        type: 'POST',
        success: function ($sOutput) {
          $('#js_global_attachment_link_cancel').show();

          if (substr($sOutput, 0, 1) == '{') {
            var $oOutput = $.parseJSON($sOutput);
            $Core.resetActivityFeedError($oOutput['error']);
            $bButtonSubmitActive = false;
            $('.activity_feed_form_button .button').addClass('button_not_active');
          } else {
            $Core.activityFeedProcess(false);

            $('#js_preview_link_attachment').html($sOutput);
            $('#global_attachment_link_holder').hide();
          }
        },
      });
  }).prop('built', true);

  $('#js_global_attachment_link_cancel').click(function () {
    $('#js_global_attachment_link_cancel').hide();
  }).prop('built', true);
};

$ActivityFeedCompleted.link = function () {
  $bButtonSubmitActive = true;

  $('#global_attachment_link_holder').show();
  $('.activity_feed_form_button .button').removeClass('button_not_active');
  $('#js_preview_link_attachment').html('');
  $('#js_global_attach_value').val('http://');
};

$ActivityFeedCompleted.photo = function () {
  $bButtonSubmitActive = true;

  $('#global_attachment_photo_file_input').val('');
  $('#btn_display_check_in').show();
};

var sToReplace = '', sOldNameToFind = '';

$Core.attachFunctionTagger = function (sSelector) {
  var customSelector = function () {
    return '_' + Math.random().toString(36).substr(2, 9);
  };

  var increment = 0;
  $(sSelector).each(function () {
    increment++;
    var t = $(this), selector = '_custom_' + customSelector() + '_' + increment;
    if (t.data('selector')) {
      t.removeClass(t.data('selector').replace('.', ''));
    }
    t.addClass(selector);
    t.data('selector', '.' + selector);
  });

  var showSelectors = function (obj, sNameToFind, sInput, iInputLength, iAtSymbol) {
    var sOut = '';
    for (var i in $Cache.users_mention) {
      if ($Cache.users_mention[i]['full_name'].toLowerCase().indexOf(sNameToFind.toLowerCase()) >= 0) {
        var sNewInputStart = sInput.substr(0, iAtSymbol).replace(/\'/g, '&#39;').replace(/\"/g, '&#34;'),
          sNewInputEnd = sInput.substr(iAtSymbol + 1 + sNameToFind.length, iInputLength).replace(/\'/g, '&#39;').replace(/\"/g, '&#34;');
        sValueStart = sNewInputStart;
        sValueEnd = sNewInputEnd;
        if (($Cache.users_mention[i]['user_image'].substr(0, 5) == 'http:') || ($Cache.users_mention[i]['user_image'].substr(0, 6) == 'https:')) {
          PF.event.trigger('urer_image_url', $Cache.users_mention[i]);
          $Cache.users_mention[i]['user_image'] = '<img src="' + $Cache.users_mention[i]['user_image'] + '" class="_image_32 image_deferred">';
        }
        var sBBTag = $Cache.users_mention[i]['is_page'] ? ($Cache.users_mention[i]['page_type'] == 1 ? 'group' : 'page') : 'user',
          iId = sBBTag == 'user' ? $Cache.users_mention[i]['user_id'] : $Cache.users_mention[i]['profile_page_id'];
        var sExtra = '';
        if (typeof $Cache.users_mention[i]['is_you'] != 'undefined' && $Cache.users_mention[i]['is_you']) {
          sExtra = ' (' + getPhrase('you') + ')';
        }
        var sItemType = '';
        if (typeof $Cache.users_mention[i]['is_page'] && $Cache.users_mention[i]['is_page']) {
          if ($Cache.users_mention[i]['page_type'] == 1) {
            sItemType = '<span class="item-type">' + getPhrase('groups') + '</span>';
          } else {
            sItemType = '<span class="item-type">' + getPhrase('pages') + '</span>';
          }
        }
        sOut += '<div class="tagFriendChooser" onclick="$(\'' +
          obj.data('selector') +
          '\').val(sValueStart + \'\' + (false ? \'@' +
          $Cache.users_mention[i]['user_name'] + '\' : \'[' + sBBTag + '=' +
          iId + ']' +
          $Cache.users_mention[i]['full_name'].replace(/\&#039;/g, '\\\'') +
          '[/' + sBBTag + ']\') + sValueEnd + \' \').putCursorAtEnd();$(\'' +
          obj.data('selector') +
          '\').siblings(\'.chooseFriend\').remove();"><div class="tagFriendChooserImage">' +
          $Cache.users_mention[i]['user_image'] + '</div><div class="tagFriendChooserInfo"><span class="item-name">' +
          (($Cache.users_mention[i]['full_name'].length > 25)
            ? ($Cache.users_mention[i]['full_name'].substr(0, 25) + '...')
            : $Cache.users_mention[i]['full_name']) + sExtra + '</span>' + sItemType + '</div></div>';
        /* just delete the fancy choose your friend and recreate it */
        sOut = sOut.replace('\n', '').replace('\r', '');
      }
    }

    $(obj.data('selector')).siblings('.chooseFriend').remove();
    if (!empty(sOut)) {
      $(obj.data('selector')).after('<div class="chooseFriend" style="width: ' +
        obj.parent().width() + 'px;">' + sOut + '</div>');
      $('.chooseFriend').mCustomScrollbar({
        theme: "minimal-dark",
      }).addClass('dont-unbind-children');
    }
  };

  $(sSelector).off('keyup').keyup(function () {
    var t = $(this);
    var sInput = t.val();
    var iInputLength = sInput.length;
    var iAtSymbol = sInput.lastIndexOf('@');
    if (sInput == '@' || empty(sInput) || iAtSymbol < 0 ||
      iAtSymbol == (iInputLength - 1)) {
      $(t.data('selector')).siblings('.chooseFriend').hide(function () {
        $(this).remove();
      });
      return;
    }

    var sNameToFind = sInput.substring(iAtSymbol + 1, iInputLength);
    if (sNameToFind === '') {
      return;
    }
    if (sNameToFind !== sOldNameToFind) {
      $.ajaxCall('feed.buildMentionCache', 'name=' + sNameToFind, 'GET')
        .done(function () {
          showSelectors(t, sNameToFind, sInput, iInputLength, iAtSymbol)
        });
      sOldNameToFind = sNameToFind;
    } else {
      showSelectors(t, sNameToFind, sInput, iInputLength, iAtSymbol)
    }
  });
}

$Behavior.tagger = function () {
  var selectors = '#js_activity_feed_form > .activity_feed_form_holder > #global_attachment_status > textarea, .js_comment_feed_textarea, .js_comment_feed_textarea_focus, #js_activity_feed_form .activity_feed_form_button_status_info > textarea, #js_activity_feed_edit_form textarea';
  $Core.attachFunctionTagger(selectors);
};

$Core.checkNewFeedAfter = function (aFeedIds) {
  var iNewCounter = 0;
  for (var i = 0; i < aFeedIds.length; ++i) {
    if ($('#js_item_feed_' + aFeedIds[i]).length) {
      continue;
    }
    iNewCounter++;
  }
  if (!iNewCounter) {
    return;
  }

  // update number
  var btn = $('#feed_check_new_count_link');
  btn.text(btn.text().replace(/\d+/, iNewCounter));

  $('#feed_check_new_count').removeClass('hide');
};

$Core.updateShareFeedCount = function (module_id, item_id, str, number) {
  var holder = $('#js_feed_like_holder_' + module_id + '_' + item_id +
    ', #js_feed_mini_action_holder_' + module_id + '_' + item_id);
  if (holder.length > 0) {
    holder.each(function () {
      var counter = $(this).find('.feed-comment-share-holder .counter:first');
      if (counter.length > 0) {
        var count = counter.text();
        if (!count) {
          count = 0;
        }
        if (str == '+') {
          count = parseInt(count) + number;
        } else {
          count = parseInt(count) - number;
        }
        count = count <= 0 ? '' : count;
        counter.text(count);
      }
    });
  }
};

$Behavior.checkForNewFeed = function () {

  if (typeof window.isRegisteredCheckForNewFeed != 'undefined' ||
    typeof window.$iCheckForNewFeedsTime === 'undefined') {
    return;
  }

  window.isRegisteredCheckForNewFeed = true;
  var iCheckForNewFeedsTime = parseInt(window.$iCheckForNewFeedsTime) * 1000;

  function _isHomePage() {
    return !!$('body#page_core_index-member #js_feed_content').length
      && !$('#sHashTagValue').length;
  }

  function _getLastFeedUpdate() {
    //jquery .data() may cached in some case, so we can't use it.
    var val = $('#js_feed_content [data-feed-update]:not(".sponsor"):first').attr('data-feed-update');
    return val ? val : 0;
  }

  function _checkForNewFeed() {

    var $ele = $('#js_feed_content');

    if (bAddingFeed == true) {
      return;
    }
    if (!_isHomePage()) {
      return;
    }

    if ($ele.data('loading')) {
      return;
    }

    $ele.data('loading', true);
    $.ajaxCall('feed.checkNew', 'iLastFeedUpdate=' + _getLastFeedUpdate()).always(function () {
      $ele.data('loading', false);
    }).done(function () {

    });
  }

  window.setInterval(_checkForNewFeed, iCheckForNewFeedsTime);

  window.loadNewFeeds = function () {
    $('#js_new_feed_update').html('');
    $.ajaxCall('feed.loadNew', 'iLastFeedUpdate=' + _getLastFeedUpdate());
  };
};

$Ready(function () {
  $('.activity_feed_form_attach a:not(.select-video-upload)').click(function () {
    $('.process-video-upload').remove();
    $('.activity_feed_form .upload_message_danger').remove();
    $('.activity_feed_form .error_message').remove();
  });
});

var editFeedStatusObject = {
  changeFormAjaxRequest: function (sFunction) {
    $sFormAjaxRequest = sFunction;
  }
}
$Core.editFeedStatus = function (params) {
  var editForm = $('#js_activity_feed_edit_form');
  var allowEdit = ['link', 'v', 'photo'];
  if (allowEdit.indexOf(params['type']) !== -1 && editForm.length && !editForm.hasClass('status-built')) {
    var textarea = editForm.find('textarea[name="val[user_status]"]');
    var allowDetectLink = ['link'];
    if (allowDetectLink.indexOf(params['type']) !== -1) {
      $Core.handlePasteInFeed(textarea);
    } else {
      setTimeout(function () {
        textarea.off('keyup');
        textarea.off('paste');
        $Core.attachFunctionTagger('#js_activity_feed_edit_form textarea');
      }, 100);
    }
    editForm.addClass('status-built');
  }
}

$Core.feed = {
  prepareHideFeed: function (aFeedIds, aUserIds) {
    aFeedIds.forEach(function (id) {
      $('.js_parent_feed_entry[data-feed-id=' + id + ']').addClass('feed_prepare_hiding');
    });
    aUserIds.forEach(function (id) {
      $('.js_hide_feed[data-user-id=' + id + ']').closest('.js_parent_feed_entry').addClass('feed_prepare_hiding');
    })
  },
  hideFeedFail: function (aFeedIds, aUserIds) {
    aFeedIds.forEach(function (id) {
      $('.js_parent_feed_entry[data-feed-id=' + id + ']').removeClass('feed_prepare_hiding');
    });
    aUserIds.forEach(function (id) {
      $('.js_parent_feed_entry[data-user-id=' + id + ']').removeClass('feed_prepare_hiding');
    })
  },
  hideFeed: function (aFeedIds, aUserIds) {
    aFeedIds.forEach(function (id) {
      $('.js_parent_feed_entry[data-feed-id=' + id + ']').hide('fast');
      var user_id = $('#hide_feed_' + id).data('user-id');
      var hide_all_phrase = oTranslations['hide_all_from_full_name'].replace('{full_name}', $('#hide_feed_' + id).data('user-full_name'));
      var hide_all_action = '$Core.feed.prepareHideFeed([], [' + user_id + ']); $.ajaxCall(\'feed.hideAllFromUser\', \'id=' + user_id + '\'); return false;';
      var undo_phrase = oTranslations['you_wont_see_this_post_in_news_feed_undo'].replace('{undo}', '<a href="javascript:void(0)" onclick="$Core.feed.unhideFeed(' + id + ')">' + oTranslations['undo'] + '</a>');

      /* Show undo form */
      var sUndo = '<div class="js_feed_undo_hide_feed_' + id + '">' +
        '<span>' + undo_phrase + '</span><br>' +
        '<a class="feed-hide-user" href="javascript:void(0);" onclick="' + hide_all_action + '"><i class="ico ico-eye-alt-blocked"></i>&nbsp;' + hide_all_phrase + '</a>' +
        '<span class="feed-delete" onclick="$(this).closest(\'div\').remove();"><i class="ico ico-close"></i></span>' +
        '</div>';
      $(sUndo).insertBefore('.js_parent_feed_entry[data-feed-id=' + id + ']');
    });
    aUserIds.forEach(function (id) {
      $('.js_hide_feed[data-user-id=' + id + ']').closest('.js_parent_feed_entry:hidden').each(function (key, ele) {
        var feed_id = $(ele).data('feed-id');
        $('.js_feed_undo_hide_feed_' + feed_id).remove();
        $(ele).remove();
      });
      $('.js_hide_feed[data-user-id=' + id + ']').closest('.js_parent_feed_entry').hide('fast');
      var hide_feed_id = $('.js_hide_feed[data-user-id=' + id + ']').first().prop('id');
      var full_name = $('#' + hide_feed_id).data('user-full_name');
      var undo_phrase = oTranslations['you_wont_see_posts_from_full_name_undo'].replace('{full_name}', '<a href="javascript:void(0);">' + full_name + '</a>').replace('{undo}', '<a href="javascript:void(0)" onclick="$Core.feed.unhideAllFromUser(' + id + ')">' + oTranslations['undo'] + '</a>');

      var first_feed_id = $('.js_hide_feed[data-user-id=' + id + ']').closest('.js_parent_feed_entry').first().prop('id');
      /* Show undo form */
      var sUndo = '<div class="js_feed_undo_hide_user_' + id + '">' +
        '<span>' + undo_phrase + '</span>' +
        '<span class="feed-delete" onclick="$(this).closest(\'div\').remove();"><i class="ico ico-close"></i></span>' +
        '</div>';
      $(sUndo).insertBefore('#' + first_feed_id);
      $([document.documentElement, document.body]).animate({
        scrollTop: $('.js_feed_undo_hide_user_' + id).first().offset().top - 200
      }, 1000);
    })
  },
  unhideFeed: function (iFeedId) {
    /* call to un-hide function */
    $.ajaxCall('feed.undoHideFeed', 'id=' + iFeedId);
    $('.js_parent_feed_entry[data-feed-id=' + iFeedId + ']').show('fast').removeClass('feed_prepare_hiding');
    $('.js_feed_undo_hide_feed_' + iFeedId).remove();
  },
  unhideAllFromUser: function (iUserId) {
    /* call to un-hide function */
    $.ajaxCall('feed.undoHideAllFromUser', 'id=' + iUserId);
    $('.js_hide_feed[data-user-id=' + iUserId + ']').closest('.js_parent_feed_entry').show('fast').removeClass('feed_prepare_hiding');
    $([document.documentElement, document.body]).animate({
      scrollTop: $('.js_feed_undo_hide_user_' + iUserId).first().offset().top - 200
    }, 1000);
    $('.js_feed_undo_hide_user_' + iUserId).remove();
  },
  searchHidden: function (form) {
    $.ajaxCall('feed.manageHidden', 'page=1&' + $(form).serialize());
  },
  unhide: function (hide_id, item_id, type_id) {
    $.ajaxCall('feed.unhide', 'hide_id=' + hide_id + '&item_id=' + item_id + '&type_id=' + type_id);
  },
  updateSelectedUnhideNumber: function () {
    var $list = $('input#feed_list_unhide');
    var $checkedList = $('input.feed_item_hidden_checkbox:checked');
    var listValues = [];
    $list.val('');
    $.each($checkedList, function (key, value) {
      listValues.push($(value).data("hid"));
    });
    $list.val(listValues.toString());

    if (listValues.length != 1)
      $('.feed-list-headline > span').text(oTranslations['number_items_selected'].replace('{number}', listValues.length));
    else
      $('.feed-list-headline > span').text(oTranslations['one_item_selected']);

    if (listValues.length)
      $('#feed_unhide_button').removeClass('disabled');
    else $('#feed_unhide_button').addClass('disabled');
  },
  selectUnhide: function (item) {
    $Core.feed.updateSelectedUnhideNumber();
  },
  multiUnhide: function () {
    var $list = $('input#feed_list_unhide');
    $.ajaxCall('feed.multiUnhide', 'ids=' + $list.val());
  },
  deleteElemsById: function (prefix, JSONids, callback) {
    JSONids.forEach(function (id) {
      $('#' + prefix + id).hide('fast', function () {
        $(this).remove();
      });
    });
    callback();
  },
  resetSelectedUnhide: function () {
    var $container = $('#feed_list_hidden');
    var $list = $container.find('input#feed_list_unhide');
    $list.val('');
    $container.find('input.feed_item_hidden_checkbox').checked = false;
    $('.feed-list-headline > span').text(oTranslations['number_items_selected'].replace('{number}', 0));
    $('#feed_unhide_button').addClass('disabled');
  },
  selectAllHiddens: function (elem) {
    $('.feed_item_hidden_checkbox').each(function (key, ele) {
      if (elem.checked) {
        ele.checked = true;
      } else {
        ele.checked = false;
      }
    });
    $('.feed_item_hidden_checkbox').trigger('change');
  },
}
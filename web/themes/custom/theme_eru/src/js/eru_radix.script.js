import 'popper.js';
//import 'bootstrap';

(function () {

  'use strict';

  Drupal.behaviors.changeMenuTabs = {
    attach: function (context) {
      jQuery('.project-block').parents('.container').find('.block--theme-eru-local-tasks').appendTo('.project-block header');
      jQuery('#photos .project__field-slider .field__item').append(jQuery('.project__field-slider .carousel-caption').addClass('description-photo'));
      if (jQuery('.element-invisible').hasClass('TRUE')){
        jQuery('.element-invisible.TRUE').removeClass('d-none');
      }
    }
  };

  Drupal.behaviors.initSite = {
    attach: function (context) {
      //Inicia funcionalidades de js y jQuery
      jQuery('.dropdown-toggle').dropdown();
      jQuery('.controles button').on('click', function(e){
        /*jQuery(this).addClass('active');       */
        if (jQuery(this).hasClass('active')) {
          jQuery('.controles button').addClass('active');
          jQuery(this).removeClass('active');
        } /*else {
          jQuery('.controles button'),removeClass('active');
          jQuery(this).addClass('active');
          alert('hola');
        }*/
      });
      /* jQuery( ".navbar-toggler", context ).click(function(e) {
        e.preventDefault();
        console.log("open");
        jQuery( ".navbar-collapse" ).toggleClass( "show" );
      }); */
    }
  };

  Drupal.behaviors.pageSearch = {
    attach: function (context) {
      //funcionalidades de la pagina de busqueda
      jQuery('#views-exposed-form-search-page-1').addClass('col-4 float-left')
        .parents('.page-search').find('.views-view-grid.cols-8').addClass('col-8 float-left');

    }
  };

  Drupal.behaviors.iconLists = {
    attach: function (context){
      jQuery('.news ul li').prepend("<i class='icon-lists'></i>");
      jQuery('.project-content ul li').prepend("<i class='icon-lists'></i>");
      jQuery('.page__content .page ul li').prepend("<i class='icon-lists'></i>");
      jQuery('.listado-transparencia').remove('.icon-lists');
    }
  };

  /*Drupal.behaviors.hoverProject = {
    attach: function (context){
      jQuery('.image-project a').hover(function() {
        jQuery(this).addClass('active');
        jQuery('.views-field-field-image-evento-hover img').addClass('d-block');
        jQuery('.views-field-field-image-evento-hover img').removeClass('d-none');
      }, function(){
        jQuery(this).removeClass('active');
        jQuery('.views-field-field-image-evento-hover img').addClass('d-none');
        jQuery('.views-field-field-image-evento-hover img').removeClass('d-block');
      });
    }
  };*/

  Drupal.behaviors.heightVideo = {
    attach: function (context){
      jQuery('.video-home iframe').height(300);
    }
  };

  Drupal.behaviors.linkproject = {
    attach: function (context) {

      function obtenerValorParametro(sParametroNombre) {
        var sPaginaURL = window.location.search.substring(1);
        var sURLVariables = sPaginaURL.split('&');
        for (var i = 0; i < sURLVariables.length; i++) {
          var sParametro = sURLVariables[i].split('=');
          if (sParametro[0] == sParametroNombre) {
            return sParametro[1].replace('%20',' ');
          }
        }
        return null;
      }

      var valor = obtenerValorParametro('tag');

      if (valor) {
        jQuery(".form-autocomplete").attr("value", decodeURIComponent(valor));
        jQuery(document).ready(function () {
          jQuery("#views-exposed-form-all-the-news-page-1 #edit-submit-all-the-news", context).click();
        });
      }

      jQuery(".project .tag_alcance a").on('click', function (e) {
        e.preventDefault();
        var $this = jQuery(this);
        var text = $this.text();
        var numberTax = $this.attr("href");
        var idioma = window.location.pathname;
        if (idioma.substr(0, 4) == "/en/"){
          window.location = "/en/noticias/?tag=" + encodeURI(text) + " (" + numberTax.substr(-2) + ")";
        }else{
          window.location = "/noticias/?tag=" + encodeURI(text) + " (" + numberTax.substr(-2) + ")";
        }
      });

    }
  };


})(jQuery, Drupal);

(function($) {
  $(document).ready(function(){
    initAccordion();
  });
  function initAccordion() {

    const $accordionHeaders = $('.accordion-header');

    if (!$accordionHeaders.length) return;

    $accordionHeaders.on('click', function () {
      const $this = $(this);
      const $item = $this.closest('.accordion-item');
      const $content = $item.find('.accordion-content');
      const $container = $this.closest('.accordion');

      if ($item.hasClass('active')) {
        $item.removeClass('active');
        $content.slideUp(300);
      } else {
        $container.find('.accordion-item').removeClass('active');
        $container.find('.accordion-content').slideUp(300);

        $item.addClass('active');
        $content.slideDown(300);
      }
    });
  }
})(jQuery);
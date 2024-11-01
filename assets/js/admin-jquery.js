(function($,wp){
    $(document).ready(function(){
        $('.AATHUAT_add_esms').on('click',function () {
            var sanpham = wp.template('devvn-tresms');
            var sanpham_tbody = $('.thuesms_tbody > tr').length;
            $('.thuesms_tbody').append( sanpham( {
                stt:  parseInt(sanpham_tbody)
            } ) );
            return false;
        });
        function reload_sort_loaisp() {
            $('.thuesms_tbody > tr').each(function (index, row) {
                $('input[type="text"], input[type="number"], select, textarea', row ).each( function( i, el ) {
                    var t = $(el);
                    t.attr( 'name', t.attr('name').replace(/\[id_([^[]*)\]/, "[id_" + index + "]" ) );
                });
            });
        }
        $('body').on('click','.AATHUAT_delete_esms',function () {
            var thisTr = $(this).closest('tr');
            thisTr.fadeOut(400,function () {
                $(this).remove();
                reload_sort_loaisp();
            });
            return false;
        });
    })
})(jQuery,wp)
jQuery(document).ready(function($){
        $(document).on('click', '#table_export', function(e){
                var theUrl=window.location.href.split('?')[0];
                var redirUrl=theUrl+'?page=export_orders';
                redirUrl+='&table_export=true';
                window.location.href=redirUrl;
        });
        $(document).on('click', '#email_export', function(e){
                var theUrl=window.location.href.split('?')[0];
                var redirUrl=theUrl+'?page=export_orders';
                redirUrl+='&email_export=true';
                window.location.href=redirUrl;
        });
})

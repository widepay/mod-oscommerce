<?php

  require('includes/application_top.php');

// if the customer is not logged on, redirect them to the shopping cart page
  if (!tep_session_is_registered('customer_id')) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }

  $orders_query = tep_db_query("select orders_id from " . TABLE_ORDERS . " where customers_id = '" . (int)$customer_id . "' order by date_purchased desc limit 1");

// redirect to shopping cart page if no orders exist
  if ( !tep_db_num_rows($orders_query) ) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }

  $orders = tep_db_fetch_array($orders_query);

  $order_id = $orders['orders_id'];

  $page_content = $oscTemplate->getContent('checkout_success');

  if ( isset($HTTP_GET_VARS['action']) && ($HTTP_GET_VARS['action'] == 'update') ) {
    tep_redirect(tep_href_link(FILENAME_DEFAULT));
  }

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_SUCCESS);

  $breadcrumb->add(NAVBAR_TITLE_1);
  $breadcrumb->add(NAVBAR_TITLE_2);

  require(DIR_WS_INCLUDES . 'template_top.php');

$total = $_GET['total'];
$transacao = $_GET['transacao'];
$linhaDigitavel = $_GET['linhaDigitavel'];
$link_boleto = $_GET['link_boleto'];

?>

<h1><?php echo HEADING_TITLE; ?></h1>

<?php echo tep_draw_form('order', tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'action=update', 'SSL')); ?>

<div class="contentContainer">
    <h4>Cobrança Gerada Com Sucesso</h4>
    <p>

        Valor: <span class="price"><strong><?php echo $total; ?></strong></span>
        <br />Referência: <span class="reference"><strong><?php echo $order_id; ?></strong></span>
        <br />Transação: <span class="venda"><strong><?php echo $transacao; ?></strong></span>

        <br />Código Boleto: <span class="venda" ><strong ><?php echo isset($linhaDigitavel) ? $linhaDigitavel : 'Clique no botão abaixo para mais detalhes'; ?></strong ></span >

        <br><br><a class="btn btn-success" href="<?php echo $link_boleto; ?>" target="_blank"><i class="icon-print"></i> Imprimir Boleto de Pagamento</a>

        <br /><br />Enviamos um e-mail com detalhes de seu pedido
        <br />Para qualquer duvida ou informação entre em contato conosco
    </p>

    <hr />
</div>

<div class="contentContainer">
  <div class="buttonSet">
    <span class="buttonAction"><?php echo tep_draw_button(IMAGE_BUTTON_CONTINUE, 'triangle-1-e', null, 'primary'); ?></span>
  </div>
</div>

</form>

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>

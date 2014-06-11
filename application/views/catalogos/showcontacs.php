<table id="gridContacs"></table>
<div id="navbar"></div>
<!--Formulario de agregar-->
<div id="dialog-form-new" title="Nuevo contacto">
	<form name="formNew" method="POST" action="<?=base_url('catalogos/saveContacto')?>">
		<div id="formNew-res" class="ui-state-error ui-corner-all">Panel</div>
		<label for="rfc">RFC</label>
		<input type="text" name="rfc" id="rfc" class="text ui-widget-content ui-corner-all" value="" 
		onkeyup="javascript:this.value=this.value.toUpperCase();"/>
		<label for="name">Razon social</label>
		<input type="text" name="name" id="name" class="text ui-widget-content ui-corner-all" value="" 
		onkeyup="javascript:this.value=this.value.toUpperCase();"/>
		<input type="hidden" name="oper" value="add"/>
	</form>
</div>

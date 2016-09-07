<?xml version="1.0" encoding="UTF-8" ?>
<!DOCTYPE xsl:stylesheet SYSTEM "ulang://i18n/constants.dtd:file">
<xsl:stylesheet version="1.0" 
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:umi="http://www.umi-cms.ru/TR/umi">
	
	<!-- Оформление этапа оплаты PURCHASE-->
	<xsl:template match="purchasing[@stage = 'payment'][@step = 'tinkoff']">
		<form action="{formAction}" method="get">
			<div>
				<xsl:text>&payment-redirect-text; Tinkoff.</xsl:text>
			</div>

			<div>
				<input type="submit" value="Оплатить" class="button big" />
			</div>
		</form>
	</xsl:template>

</xsl:stylesheet>
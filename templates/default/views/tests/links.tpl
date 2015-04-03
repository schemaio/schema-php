{* Test order links *}

{$order = get("/orders/:last")}

{$order|dump}

<ul>
{foreach $order.items as $item}
	<li>{$item.product.name} : {$item.product.price}</li>
{/foreach}
</ul>
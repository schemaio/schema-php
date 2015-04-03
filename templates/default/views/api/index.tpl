{controller "api.index"}

<form role="form" method="get" action="" class="form-inline">
	<select name="method" class="form-control" style="width:90px">
		{foreach [GET, PUT, POST, DELETE] as $m}
			<option {if $method == $m}selected="selected"{/if}>{$m}</option>
		{/foreach}
	</select>
	<select name="url" class="form-control" style="width:130px">
		{* Output models then metamodels in order *}
		{foreach $index.options as $model}
			<option {if $url == $model.url}selected="selected"{/if} value="{$model.url}">{$model.url}</option>
			{if !isset($model.children)}
				{continue}
			{/if}
			{foreach $model.children as $child}
				<option {if $url == $child.url}selected="selected"{/if} value="{$child.url}">{$child.url}</option>
			{/foreach}
		{/foreach}
	</select>
	<input type="text" name="rel_url" value="{$rel_url|escape|default:""}" placeholder="URI" id="urlinput" size="40" class="form-control" style="width:400px" />
	<button type="submit" class="btn btn-default">Go</button>
	{if $method !== 'GET' || $rel_url}
		<a href="?method=GET&amp;url={$url}" class="btn btn-default">Clear</a>
	{/if}
</form>

{if $method}
	<br />
	<code>
		<span>{$method|upper} {$index.url} ({$index.timing|number_format:4} seconds)</span>
		<pre>{$index.result|json_print|escape}</pre>
	</code>
{/if}

<script type="text/javascript">
	document.getElementById('urlinput').focus();
</script>

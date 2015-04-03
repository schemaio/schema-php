<!DOCTYPE html>
<html>
<head>
	<title>API {if isset($request.client)}({$request.client}){/if}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css">
	<link rel="icon" type="image/png" href="{'/favicon.png'|asset_url}">
</head>
<body>

	<div style="padding: 20px">

		{$content_for_layout}

	</div>

</body>
</html>

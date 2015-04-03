## fwd-php-client

*Forward is a platform to build and scale ecommerce.* This is the PHP client library.

Create an API account at https://getfwd.com

## Usage example

	<?php require_once("/path/to/fwd-php-client/lib/Forward.php");

	$fwd = new Forward\Client('client_id', 'client_key');

	$products = $fwd->get('/products', array('color' => 'blue'));

	print_r($products);

## Documentation

See <http://getfwd.com/docs/clients#php> for more API docs and usage examples

## Contributing

Pull requests are welcome

## License

Apache2.0

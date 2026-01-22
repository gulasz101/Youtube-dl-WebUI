<?php

use App\Utils\Session;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;

use function StrictHelpers\ob_get_contents;

$session = Session::getInstance();
$loginError = "";

/**
 * @var ServerRequestInterface $request
 */


if ($request->getMethod() === 'POST') {
    if ($session->login($request->getParsedBody()['password'] ?? null)) {

        return new Response(302, ['Location' => 'index.php']);
    }

    $loginError = "Wrong password !";
}


ob_start();

require 'views/header.php';

?>

<div class="container my-4">
  <?php
  if ($loginError !== "") {
      ?>
    <div class="alert alert-danger"><?php echo $loginError; ?></div>
  <?php
  }
?>
  <form action="/login.php" method="POST">
    <div class="row my-3 justify-content-md-center">
      <div class="col-md-4">
        <h2>Login</h2>
      </div>
    </div>
    <div class="row my-3 justify-content-md-center">
      <div class="col-lg-4">
        <input class="form-control" id="password" name="password" placeholder="Password" type="password">
      </div>
    </div>
    <div class="row my-3 justify-content-md-center">
      <div class="col-lg-4">
        <button type="submit" class="btn btn-primary">Sign in</button>
      </div>
    </div>
  </form>
</div>


<?php

require 'views/footer.php';
$responseBody = ob_get_contents();
ob_end_clean();

return new Response(body: $responseBody);

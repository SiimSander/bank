<?php /** @var string $uri */ ?>
<nav>
	<?php if ($uri !== '/' && $uri !== '/index.php'): ?>
		<a href="/">Home</a> <br>
	<?php endif; ?>

	<?php if ($uri !== '/signup'): ?>
		<a href="/signup">Sign Up</a> <br>
	<?php endif; ?>

	<?php if ($uri !== '/login'): ?>
		<a href="/login">Log In</a> <br>
	<?php endif; ?>

	<?php if ($uri !== '/about'): ?>
		<a href="/about">About</a> <br>
	<?php endif; ?>

	<?php if ($uri !== '/settings'): ?>
		<a href="/settings">Settings</a>
	<?php endif; ?>

	<?php if (isset($_SESSION['username'])): ?>
		<p>Logged in as <?php echo htmlspecialchars($_SESSION['username']); ?></p>
		<a href="/logout">Log Out</a> <br>
	<?php endif; ?>
</nav>
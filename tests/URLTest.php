<?php
use EmPHyre\URL;
use PHPUnit\Framework\TestCase;

final class URLTest extends TestCase
{
    // Regression: #8. The link text was not escaped at all and the href's single quotes weren't.
    public function testRedirectLinkEscapesTheLocation()
    {
        $link = new \ReflectionMethod(URL::class, 'redirectLink');
        $link->setAccessible(true);

        $html = $link->invoke(null, "/x?a='><script>alert(1)</script>");

        $this->assertStringNotContainsString('<script>', $html, 'no markup from the location');
        $this->assertStringNotContainsString("='>", $html, 'the href quote is not closed by the location');
        $this->assertSame(
            "Redirecting to: <a href='/x?a=&#039;&gt;&lt;script&gt;alert(1)&lt;/script&gt;'>"
            . "/x?a=&#039;&gt;&lt;script&gt;alert(1)&lt;/script&gt;</a>",
            $html
        );
    }
}

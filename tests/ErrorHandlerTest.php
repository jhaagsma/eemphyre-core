<?php
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/ErrorHandler.function.php';

/** The pure helpers of ErrorHandler.function.php (#15): IRC relay text and CSV fields. */
final class ErrorHandlerTest extends TestCase
{
    public function testControlCharactersCannotStartANewIrcLine()
    {
        $out = emphyreErrorRelayText("bad input\r\nPRIVMSG #ops :pwned\x00\x07end");
        $this->assertStringNotContainsString("\r", $out, 'no CR');
        $this->assertStringNotContainsString("\n", $out, 'no LF');
        $this->assertSame(0, preg_match('/[\x00-\x1f\x7f]/', $out), 'no control characters at all');
        $this->assertSame('bad input PRIVMSG #ops :pwned end', $out);
    }

    public function secretUrls()
    {
        return [
            ['GET /feed?apicode=abc123&x=1', 'GET /feed?apicode=***&x=1'],
            ['https://h/p?player_site_id=99&key=s3cr3t', 'https://h/p?player_site_id=***&key=***'],
            ['token=abc password=hunter2', 'token=*** password=***'],
            ['APIKEY=Z,next', 'APIKEY=***,next'],
            ['PHPSESSID=abc; x=1', 'PHPSESSID=***; x=1'],
            ['secret_key=a&client_secret=b&auth_token=c&new_password=d', 'secret_key=***&client_secret=***&auth_token=***&new_password=***'],
            ['body {"apicode":"abc","n":1}', 'body {"apicode":"***","n":1}'],
            ["['token' => 'abc\\'d']", "['token' => '***']"],
            ['GET /x?apicode%3Dabc%26y', 'GET /x?apicode%3D***'],
            ['https://bob:pw@host/p', 'https://bob:***@host/p'],
            ['session_id=abc&x', 'session_id=***&x'],
            ['{"player_site_id": 99, "n": 1}', '{"player_site_id": ***, "n": 1}'],
            ['host:8080/p and http://h:8080/p', 'host:8080/p and http://h:8080/p'],
        ];
    }

    /** @dataProvider secretUrls */
    public function testSecretParametersAreMasked($in, $expected)
    {
        $this->assertSame($expected, emphyreErrorRelayText($in));
    }

    public function testOrdinaryWordsAreKept()
    {
        $this->assertSame('monkey=1 keyboard=2 turkey', emphyreErrorRelayText('monkey=1 keyboard=2 turkey'),
            'only whole parameter names are masked');
    }

    public function testLengthIsCapped()
    {
        $out = emphyreErrorRelayText(str_repeat('a', 1000), 50);
        $this->assertSame(50, strlen($out));
        $this->assertStringEndsWith('...', $out);
    }

    public function testCsvFieldEscapesQuotesAndNewlines()
    {
        $this->assertSame('"say ""hi"" there"', emphyreErrorCsvField("say \"hi\"\r\nthere"));
        $this->assertSame('"42"', emphyreErrorCsvField(42));
    }

    public function testHostileInputStaysFast()
    {
        $inputs = [
            '{"token":"' . str_repeat('\\', 5000) . 'x',      // unterminated JSON value full of backslashes
            str_repeat('a-', 50000) . 'b',                      // long prefix-like run
            str_repeat('"key":', 5000),
        ];
        foreach ($inputs as $in) {
            $start = microtime(true);
            $out   = emphyreErrorRelayText($in);
            $this->assertLessThan(0.5, microtime(true) - $start, 'linear-time cleaning');
            $this->assertNotSame('', $out, 'no blank relay line');
        }
    }
}

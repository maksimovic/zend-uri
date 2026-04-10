<?php

use PHPUnit\Framework\TestCase;

class Zend_UriTest extends TestCase
{
    /**
     * @var array
     */
    protected $notices;

    /**
     * @var int
     */
    protected $errorReporting;

    /**
     * @var string
     */
    protected $displayErrors;

    /**
     * @var string
     */
    protected $error;

    public function setUp(): void
    {
        $this->notices = array();
        $this->errorReporting = error_reporting();
        $this->displayErrors  = ini_get('display_errors');
    }

    public function tearDown(): void
    {
        error_reporting($this->errorReporting);
        ini_set('display_errors', $this->displayErrors);
    }

    public function testSchemeEmpty(): void
    {
        $this->_testInvalidUri('', '/empty/i');
        $this->_testInvalidUri('://www.zend.com', '/empty/i');
    }

    public function testSchemeUnsupported(): void
    {
        $this->_testInvalidUri('unsupported', '/unsupported/i');
        $this->_testInvalidUri('unsupported://zend.com', '/unsupported/i');
    }

    public function testSchemeIllegal(): void
    {
        $this->_testInvalidUri('!@#$%^&*()', '/illegal/i');
    }

    public function testSchemeHttp(): void
    {
        $this->_testValidUri('http');
    }

    public function testSchemeHttps(): void
    {
        $this->_testValidUri('https');
    }

    public function testSchemeMailto(): void
    {
        $this->markTestIncomplete('Zend_Uri_Mailto is not implemented yet');
        $this->_testValidUri('mailto');
    }

    /**
     * Tests that Zend_Uri::setConfig() allows Zend_Config
     *
     * @group ZF-5578
     */
    public function testSetConfigWithArray(): void
    {
        Zend_Uri::setConfig(array('allow_unwise' => true));
        $this->addToAssertionCount(1);
    }

    /**
     * Tests that Zend_Uri::setConfig() allows Array
     *
     * @group ZF-5578
     */
    public function testSetConfigWithZendConfig(): void
    {
        Zend_Uri::setConfig(new Zend_Config(array('allow_unwise' => true)));
        $this->addToAssertionCount(1);
    }

    /**
     * Tests that Zend_Uri::setConfig() throws Zend_Uri_Exception if no array
     * nor Zend_Config is given as first parameter
     *
     * @group ZF-5578
     */
    public function testSetConfigInvalid(): void
    {
        $this->expectException(Zend_Uri_Exception::class);
        Zend_Uri::setConfig('This should cause an exception');
    }

    /**
     * Tests that if an exception is thrown when calling the __toString()
     * method an empty string is returned and a Warning is triggered, instead
     * of a Fatal Error being triggered.
     *
     * @group ZF-10405
     */
    public function testToStringRaisesWarningWhenExceptionCaught(): void
    {
        $uri = Zend_Uri::factory('http://example.com', 'Zend_Uri_ExceptionCausing');

        set_error_handler(array($this, 'handleErrors'), E_USER_WARNING);

        $text = sprintf('%s', $uri);

        restore_error_handler();

        $this->assertTrue(empty($text));
        $this->assertTrue(isset($this->error));
        $this->assertStringContainsString('Exception in getUri()', $this->error);
    }

    /**
     * Error handler for testExceptionThrownInToString()
     *
     * @group ZF-10405
     */
    public function handleErrors($errno, $errstr, $errfile = '', $errline = 0, array $errcontext = array()): bool
    {
        $this->error = $errstr;
        return true;
    }

    /**
     * Tests that an invalid $uri throws an exception and that the
     * message of that exception matches $regex.
     *
     * @param string $uri
     * @param string $regex
     */
    protected function _testInvalidUri($uri, $regex): void
    {
        $e = null;
        try {
            $uri = Zend_Uri::factory($uri);
        } catch (Zend_Uri_Exception $e) {
            $this->assertMatchesRegularExpression($regex, $e->getMessage());
            return;
        }
        $this->fail('Zend_Uri_Exception was expected but not thrown');
    }

    /**
     * Tests that a valid $uri returns a Zend_Uri object.
     *
     * @param string $uri
     */
    protected function _testValidUri($uri, $className = null)
    {
        $uri = Zend_Uri::factory($uri, $className);
        $this->assertTrue($uri instanceof Zend_Uri, 'Zend_Uri object not returned.');
        return $uri;
    }

    public function testFactoryWithUnExistingClassThrowException(): void
    {
        $this->expectException(Zend_Uri_Exception::class);
        $this->expectExceptionMessage('"This_Is_An_Unknown_Class" not found');
        Zend_Uri::factory('http://example.net', 'This_Is_An_Unknown_Class');
    }

    public function testFactoryWithExistingClassButNotImplementingZendUriThrowException(): void
    {
        $this->expectException(Zend_Uri_Exception::class);
        $this->expectExceptionMessage('"Fake_Zend_Uri" is not an instance of Zend_Uri');
        Zend_Uri::factory('http://example.net', 'Fake_Zend_Uri');
    }

    public function testFactoryWithExistingClassReturnObject(): void
    {
        $uri = $this->_testValidUri('http://example.net', 'Zend_Uri_Mock');
        $this->assertTrue($uri instanceof Zend_Uri_Mock, 'Zend_Uri_Mock object not returned.');
    }
}

class Zend_Uri_Mock extends Zend_Uri
{
    protected function __construct($scheme, $schemeSpecific = '') { }
    public function getUri() { return ''; }
    #[ReturnTypeWillChange]
    public function valid() { return true; }
}

class Zend_Uri_ExceptionCausing extends Zend_Uri
{
    protected function __construct($scheme, $schemeSpecific = '') { }
    #[ReturnTypeWillChange]
    public function valid() { return true; }
    public function getUri()
    {
        throw new Exception('Exception in getUri()');
    }
}

class Fake_Zend_Uri
{
}

/**
 * Minimal Zend_Config stub for testing Zend_Uri::setConfig()
 * when the full zend-config package is not installed.
 */
if (!class_exists('Zend_Config')) {
    class Zend_Config implements Countable, Iterator
    {
        protected $_data = [];
        protected $_index = 0;

        public function __construct(array $array)
        {
            $this->_data = $array;
        }

        public function toArray(): array
        {
            return $this->_data;
        }

        public function count(): int
        {
            return count($this->_data);
        }

        public function current(): mixed
        {
            $keys = array_keys($this->_data);
            return $this->_data[$keys[$this->_index]] ?? false;
        }

        public function key(): mixed
        {
            $keys = array_keys($this->_data);
            return $keys[$this->_index] ?? null;
        }

        public function next(): void
        {
            $this->_index++;
        }

        public function rewind(): void
        {
            $this->_index = 0;
        }

        public function valid(): bool
        {
            return $this->_index < count($this->_data);
        }
    }
}

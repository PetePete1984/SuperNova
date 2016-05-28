<?php

/**
 * Class DbSqlAwareTest
 *
 * @coversDefaultClass DbSqlAware
 */
class DbSqlAwareTest extends PHPUnit_Framework_TestCase {

  /**
   * @var DbSqlAware $object
   */
  protected $object;

  public function setUp() {
    parent::setUp();
    $this->object = DbSqlAware::build();
  }

  public function tearDown() {
    unset($this->object);
    parent::tearDown(); // TODO: Change the autogenerated stub
  }

  /**
   * @covers ::__construct
   * @covers ::build
   */
  public function testBuild() {
    $this->assertEquals('DbSqlAware', get_class($test = DbSqlAware::build(null)));
    unset($test);
  }

  /**
   * @covers ::escapeString
   */
  public function testEscapeString() {
    $this->assertEquals('', invokeMethod($this->object, 'escapeString', array('')));
    $this->assertEquals('test', invokeMethod($this->object, 'escapeString', array('test')));
    $this->assertEquals('t\\\'e\\"s\\\\t', invokeMethod($this->object, 'escapeString', array('t\'e"s\\t')));
  }


  public function dataQuoteStringAsFieldByRef() {
    return array(
      array('', ''),
      array('*', '*'),
      array('test', '`test`'),
    );
  }

  /**
   * @param $value
   * @param $expected
   *
   * @dataProvider dataQuoteStringAsFieldByRef
   *
   * @covers ::quoteStringAsFieldByRef
   */
  public function testQuoteStringAsFieldByRef($value, $expected) {
    invokeMethod($this->object, 'quoteFieldSimpleByRef', array(&$value));
    $this->assertEquals($expected, $value);
  }


  public function dataMakeFieldFromString() {
    return array(
      array('', ''),
      array('*', '*'),
      array('test', '`test`'),
      array('table.*', '`table`.*'),
      array('table.field', '`table`.`field`'),
    );
  }

  /**
   * @param $value
   * @param $expected
   *
   * @dataProvider dataMakeFieldFromString
   *
   * @covers ::makeFieldFromString
   * @covers ::quoteStringAsFieldByRef
   */
  public function testMakeFieldFromString($value, $expected) {
    $this->assertEquals($expected, invokeMethod($this->object, 'quoteField', array(&$value)));
  }


  public function dataMakeAliasFromField() {
    return array(
      array('max', '*', 'maxValue'),
      array('max', 'field', 'maxField'),
      array('max', 'table.*', 'maxTable'),
      array('max', 'table.field', 'maxTableField'),
    );
  }

  /**
   * @param string $functionName
   * @param string $field
   * @param string $expected
   *
   * @dataProvider dataMakeAliasFromField
   *
   * @covers ::makeAliasFromField
   * @covers       DbSqlHelper::UCFirstByRef
   */
  public function testMakeAliasFromField($functionName, $field, $expected) {
    $this->assertEquals($expected, invokeMethod($this->object, 'aliasFromField', array($functionName, $field)));
  }

}

<?php
namespace Civi\PrReport;

class IssueParser {

  private $issuePatterns;

  /**
   * IssueExtractor constructor.
   * @param array $issuePatterns
   *   Ex: $prefixes['/^(CRM-[0-9]+)$/'] = 'https://issues.civicrm.org/jira/browse/$1';
   */
  public function __construct($issuePatterns) {
    $this->issuePatterns = $issuePatterns;
  }

  /**
   * Parse all the words in $string to identify any issue codes
   * (such as CRM-12345).
   *
   * @param string $string
   *   Ex: "Frobnicate widgets (for CRM-12345)."
   * @return array
   *   List of issues.
   *   Ex: array('CRM-12345' => 'http://example.com/issues/CRM-12345').
   */
  public function parse($string) {
    $words = preg_split('/[ \r\n\t,\(\):\.!\?]/', $string);
    $matches = array();

    foreach ($this->issuePatterns as $pattern => $url) {
      foreach (preg_grep($pattern, $words) as $word) {
        $matches[$word] = preg_replace($pattern, $url, $word);
      }
    }

    return $matches;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\cmc\Sniffs\Twig;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Detects direct field access in Twig templates.
 */
class DirectFieldAccessSniff implements Sniff {

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_INLINE_HTML];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr) {
    // Only process .twig files.
    $fileExtension = strtolower(substr($phpcsFile->getFilename(), -5));
    if ($fileExtension !== '.twig') {
      return;
    }

    $tokens = $phpcsFile->getTokens();
    $content = $tokens[$stackPtr]['content'];

    // Look for content.field_*, node.field_*, media.field_*, etc.
    if (preg_match('/[a-z]+\.field_[a-z]+/', $content, $matches)) {
      // Do not error out if this is a case we know it's not an issue.
      $file_content = $phpcsFile->getTokensAsString(0, count($tokens));
      if ($this->shouldSkip($file_content)) {
        return;
      }

      $error = 'Direct field access using "%s" is not recommended. Try to always render full render arrays that come from the backend.';
      $data = [$matches[0]];
      $phpcsFile->addWarning($error, $stackPtr, 'DirectFieldAccess', $data);
    }
  }

  /**
   * Detects whether we should skip an identified failure.
   *
   * @param string $file_content
   *   The full contents of the file being sniffed.
   *
   * @return bool
   *   TRUE if at least one of the below conditions are met:
   *    1- If {{ content }} is being printed in the same template;
   *    2- If {{ content['#cache'] }} is being printed as-is;
   *    3- If devs assume responsibility and opt-out with the special syntax
   *     {# cmc_direct_field_access_sniff_opt_out #}.
   *   Will return FALSE otherwise.
 */
  private function shouldSkip(string $file_content): bool {
    $matches = [];
    // Pattern {{ content }}.
    if (preg_match('/{{(\s)?content(\s)?}}/', $file_content, $matches)) {
      return TRUE;
    }
    // Pattern {{ content['#cache'] }}.
    $skip_pattern = <<<TEXT
{{(\s)?content\[('|")#cache('|")\](\s)?}}
TEXT;
    if (preg_match($skip_pattern, $file_content, $matches)) {
      return TRUE;
    }
    // Pattern {# cmc_direct_field_access_sniff_opt_out #}.
    if (preg_match('/{#(\s)cmc_direct_field_access_sniff_opt_out(\s)?#}/', $file_content, $matches)) {
      return TRUE;
    }

    return FALSE;
  }

}

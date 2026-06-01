<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer;
use PhpCsFixer\Fixer\Basic\BracesFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([__DIR__]);

    $ecsConfig->sets([
        SetList::PSR_12,
        SetList::CLEAN_CODE,
    ]);

    $ecsConfig->rules([
        // php-cs-fixer
        MbStrFunctionsFixer::class,
        BracesFixer::class,
        OrderedClassElementsFixer::class,
        ReturnTypeDeclarationFixer::class,
        StrictComparisonFixer::class,
        StrictParamFixer::class,
        BlankLineBeforeStatementFixer::class,

        // slevomat - arrays
        \SlevomatCodingStandard\Sniffs\Arrays\DisallowImplicitArrayCreationSniff::class,
        \SlevomatCodingStandard\Sniffs\Arrays\MultiLineArrayEndBracketPlacementSniff::class,
        \SlevomatCodingStandard\Sniffs\Arrays\SingleLineArrayWhitespaceSniff::class,
        \SlevomatCodingStandard\Sniffs\Arrays\TrailingArrayCommaSniff::class,

        // slevomat - classes
        \SlevomatCodingStandard\Sniffs\Classes\ClassConstantVisibilitySniff::class,
        \SlevomatCodingStandard\Sniffs\Classes\DisallowMultiConstantDefinitionSniff::class,
        \SlevomatCodingStandard\Sniffs\Classes\DisallowMultiPropertyDefinitionSniff::class,
        \SlevomatCodingStandard\Sniffs\Classes\ModernClassNameReferenceSniff::class,
        \SlevomatCodingStandard\Sniffs\Classes\PropertyDeclarationSniff::class,

        // slevomat - control structures
        \SlevomatCodingStandard\Sniffs\ControlStructures\AssignmentInConditionSniff::class,
        \SlevomatCodingStandard\Sniffs\ControlStructures\DisallowContinueWithoutIntegerOperandInSwitchSniff::class,
        \SlevomatCodingStandard\Sniffs\ControlStructures\DisallowYodaComparisonSniff::class,
        \SlevomatCodingStandard\Sniffs\ControlStructures\NewWithParenthesesSniff::class,
        \SlevomatCodingStandard\Sniffs\ControlStructures\RequireNullCoalesceEqualOperatorSniff::class,
        \SlevomatCodingStandard\Sniffs\ControlStructures\RequireNullCoalesceOperatorSniff::class,

        // slevomat - exceptions
        \SlevomatCodingStandard\Sniffs\Exceptions\DeadCatchSniff::class,

        // slevomat - functions
        \SlevomatCodingStandard\Sniffs\Functions\StrictCallSniff::class,
        \SlevomatCodingStandard\Sniffs\Functions\StaticClosureSniff::class,
        \SlevomatCodingStandard\Sniffs\Functions\UnusedParameterSniff::class,
        \SlevomatCodingStandard\Sniffs\Functions\UselessParameterDefaultValueSniff::class,

        // slevomat - namespaces
        \SlevomatCodingStandard\Sniffs\Namespaces\UseDoesNotStartWithBackslashSniff::class,
        \SlevomatCodingStandard\Sniffs\Namespaces\UselessAliasSniff::class,

        // slevomat - php
        \SlevomatCodingStandard\Sniffs\PHP\ShortListSniff::class,
        \SlevomatCodingStandard\Sniffs\PHP\TypeCastSniff::class,

        // slevomat - type hints
        \SlevomatCodingStandard\Sniffs\TypeHints\NullableTypeForNullDefaultValueSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSpacingSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSpacingSniff::class,
        \SlevomatCodingStandard\Sniffs\TypeHints\UselessConstantTypeHintSniff::class,

        // slevomat - variables
        \SlevomatCodingStandard\Sniffs\Variables\DisallowSuperGlobalVariableSniff::class,
    ]);

    $skips = [
        __DIR__ . '/vendor',

        // inherited skips
        \PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\RequireStrictTypesSniff::class,
        \SlevomatCodingStandard\Sniffs\Functions\StaticClosureSniff::class,
    ];

    foreach ([
                 '_ide_helper.php',
                 '_ide_helper_models.php',
                 '.phpstorm.meta.php',
                 '_enumhancer.php',
             ] as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            $skips[] = __DIR__ . '/' . $file;
        }
    }

    $ecsConfig->skip($skips);
};

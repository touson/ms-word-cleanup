# ms-word-cleanup
Cleans all the crap out of HTML produced by Microsoft Word

This package is a conversion of the work started by [WebmasterSherpa](https://www.webmastersherpa.com/ms-word-html-cleanup-tool/) into a class based composer package.

## Installation

You can add this library as a local, per-project dependency to your project using [Composer](https://getcomposer.org/):

    composer require touson/mswordcleanup

## Usage examples

```php
$cleaner = new touson\Cleaner('some HTML');
// Returns the cleaned HTML
$cleaner->cleanHtml();

// Or you can use it statically.  This will new up an instance of the Cleaner class, run the cleanHtml() method and return the result
Cleaner::clean('some HTML');
```
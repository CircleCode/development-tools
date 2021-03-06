<?php

require_once 'initializeAutoloader.php';

use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;
use Dcp\DevTools\Stub\Stub;
use Dcp\DevTools\Utils\ConfigFile;

$getopt = new Getopt(array(
    (new Option('o', 'output', Getopt::REQUIRED_ARGUMENT))->setDescription('output dir (nedded)'),
    (new Option('s', 'sourcePath',
        Getopt::REQUIRED_ARGUMENT))->setDescription('path of the source of the module (nedded)')
        ->setValidation(function ($inputDir) {
            if (!is_dir($inputDir)) {
                print "The input dir must be a valid dir ($inputDir)";
                return false;
            }
            return true;
        }),
    (new Option('f', 'file', Getopt::REQUIRED_ARGUMENT)),
    (new Option('h', 'help', Getopt::NO_ARGUMENT))->setDescription('show the usage message')
));

try {
    $getopt->parse();

    if (isset($getopt["help"])) {
        echo $getopt->getHelpText();
        exit(0);
    }

    $error = array();
    if (!isset($getopt['output'])) {
        $error[] = "You need to set the output path -o or --output";
    }

    if (!isset($getopt['s'])) {
        $error[] = "You need to set the output path for the file -s or --sourcePath";
    }

    if (!empty($error)) {
        echo join("\n", $error);
        echo "\n" . $getopt->getHelpText();
        exit(42);
    }

    if (!is_dir($getopt['output'])) {
        mkdir($getopt['output'], 0777, true);
    }

    $inputDir = $getopt['sourcePath'];

    $realDir = realpath($inputDir);
    if (is_dir($realDir)) {
        $inputDir = $realDir;
    }

    $globRecursive = function ($pattern, $flags = 0) use (&$globRecursive) {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $globRecursive($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags));
        }

        return $files;
    };

    $config = new ConfigFile($inputDir);

    $csvParam = $config->get(
        'csvParam', [
        "enclosure" => '"',
        "delimiter" => ';'
    ], ConfigFile::GET_MERGE_DEFAULTS
    );

    $enclosure = $csvParam["enclosure"];
    $delimiter = $csvParam["delimiter"];

    $structFilePattern = '#/(?P<familyName>[^/]+)__(?:STRUCT|PARAM|CONFIG|WORKFLOW).csv#';
    $family = null;
    $sortedFiles = [];
    if (isset($getopt['file'])) {
        $matches = [];
        if (preg_match($structFilePattern, $getopt['file'], $matches)) {
            $family = $matches['familyName'];
        } else {
            $family = $getopt['file'];
        }
    }

    $files = array_merge(
        $globRecursive($inputDir . DIRECTORY_SEPARATOR . "*__STRUCT.csv"),
        $globRecursive($inputDir . DIRECTORY_SEPARATOR . "*__PARAM.csv"),
        $globRecursive($inputDir . DIRECTORY_SEPARATOR . "*__CONFIG.csv"),
        $globRecursive($inputDir . DIRECTORY_SEPARATOR . "*__WORKFLOW.csv")
    );

    array_walk($files, function ($filePath) use ($structFilePattern, &$sortedFiles) {
        $matches = [];
        if (preg_match($structFilePattern, $filePath, $matches)) {
            if(!isset($sortedFiles[$matches['familyName']])) {
                $sortedFiles[$matches['familyName']] = [];
            }
            $sortedFiles[$matches['familyName']][] = $filePath;
        } else {
            $sortedFiles[$filePath] = $filePath;
        }
    });


    if (!empty($family)) {
        $stub = new Stub($enclosure, $delimiter);
        $stub->generate($sortedFiles[$family], $getopt['output']);
    } else {
        foreach ($sortedFiles as $currentFilesList) {
            $stub = new Stub($enclosure, $delimiter);
            $stub->generate($currentFilesList, $getopt['output']);
        }
    }
} catch (UnexpectedValueException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $getopt->getHelpText();
    exit(1);
}

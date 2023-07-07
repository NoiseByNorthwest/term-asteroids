<?php

$results = [];
foreach(array_reverse(glob('.tmp/benchmark-*.json')) as $resultFileName) {
    $results[] = json_decode(file_get_contents($resultFileName), associative: true);
}

assert(count($results) === 4);

$phpVersion = $results[0]['phpVersion'];
$cpu = $results[0]['cpu'];

foreach ($results as $result) {
    assert($result['phpVersion'] === $phpVersion);
    assert($result['cpu'] === $cpu);
}

$rows = [
    [
        '',
        'Native Renderer + JIT',
        'Native Renderer',
        'PHP Renderer + JIT',
        'PHP Renderer',
    ]
];

$rows[] = [
    'Execution time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fs', $result['stats']['totalTime']);
        },
        $results
    )
];

$rows[] = [
    'Rendered frames',
    ...array_map(
        function (array $result) {
            return $result['stats']['renderedFrameCount'];
        },
        $results
    )
];

$rows[] = [
    'Average frame time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fms', 1000 * $result['stats']['totalTime'] / $result['stats']['renderedFrameCount']);
        },
        $results
    )
];

$rows[] = [
    'Average framerate',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1f FPS', $result['stats']['renderedFrameCount'] / $result['stats']['totalTime']);
        },
        $results
    )
];

$rows[] = [
    'Average gameplay+physic time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fms', 1000 * $result['stats']['nonRenderingTime'] / $result['stats']['renderedFrameCount']);
        },
        $results
    )
];

$rows[] = [
    'Average rendering time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fms', 1000 * $result['stats']['renderingTime'] / $result['stats']['renderedFrameCount']);
        },
        $results
    )
];

$rows[] = [
    'Average drawing time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fms', 1000 * $result['stats']['drawingTime'] / $result['stats']['renderedFrameCount']);
        },
        $results
    )
];

$rows[] = [
    'Average update time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fms', 1000 * $result['stats']['updateTime'] / $result['stats']['renderedFrameCount']);
        },
        $results
    )
];

$rows[] = [
    'Average flushing time',
    ...array_map(
        function (array $result) {
            return sprintf('%5.1fms', 1000 * $result['stats']['flushingTime'] / $result['stats']['renderedFrameCount']);
        },
        $results
    )
];

echo <<<EOS
    ## Benchmark results
    
    PHP version: $phpVersion
    
    CPU: $cpu
    

    EOS;

foreach ($rows as $k => $row) {
    echo '|', implode(
        '|',
        array_map(
            fn ($e) => sprintf(' %30s ', $e),
            $row
        )
    ), "|\n";

    if ($k === 0) {
        echo '|', implode(
            '|',
            array_map(
                fn() => ' ' . str_repeat('-', 30) . ' ',
                $row
            )
        ), "|\n";
    }
}
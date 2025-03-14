<?php
// Get API key from environment variable
$apiKey = getenv('TMDB_API_KEY');
$outputFile = "movie_of_the_day.json";
$logFile = "fetch_log.txt";
$movieListFile = "movie_list.json";

function logMessage($message) {
    global $logFile;
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logMessage("Starting movie fetch process");

// Load movie list
if (!file_exists($movieListFile)) {
    logMessage("ERROR: Movie list file not found");
    exit(1);
}

$movieList = json_decode(file_get_contents($movieListFile), true);
$movies = $movieList['movies'];

// Calculate which movie to use based on day of year
$dayOfYear = date("z") + 1; // 1-366
$index = ($dayOfYear - 1) % count($movies);
$movie = $movies[$index];

logMessage("Selected movie: {$movie['title']} (ID: {$movie['id']}) for day {$dayOfYear}");

// Fetch detailed movie information from TMDB
$movieUrl = "https://api.themoviedb.org/3/movie/{$movie['id']}?api_key={$apiKey}&language=en-US&append_to_response=credits,images";

try {
    $response = file_get_contents($movieUrl);
    
    if ($response === false) {
        throw new Exception("Failed to get API response");
    }
    
    $movieData = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . json_last_error_msg());
    }
    
    // Process movie data
    $director = "";
    foreach ($movieData['credits']['crew'] as $crew) {
        if ($crew['job'] === 'Director') {
            $director = $crew['name'];
            break;
        }
    }
    
    // Get top 5 cast members
    $cast = array_slice(array_map(function($actor) {
        return $actor['name'];
    }, $movieData['credits']['cast']), 0, 5);
    
    // Format movie data for your app
    $movieOfTheDay = [
        "version" => "1.0",
        "lastUpdated" => date("Y-m-d"),
        "movie" => [
            "id" => $movieData['id'],
            "title" => $movieData['title'],
            "tagline" => $movieData['tagline'],
            "overview" => $movieData['overview'],
            "releaseDate" => $movieData['release_date'],
            "runtime" => $movieData['runtime'],
            "voteAverage" => $movieData['vote_average'],
            "posterPath" => "https://image.tmdb.org/t/p/w500" . $movieData['poster_path'],
            "backdropPath" => "https://image.tmdb.org/t/p/original" . $movieData['backdrop_path'],
            "director" => $director,
            "cast" => $cast,
            "genres" => array_map(function($genre) {
                return $genre['name'];
            }, $movieData['genres'])
        ]
    ];
    
    // Save to JSON file
    file_put_contents($outputFile, json_encode($movieOfTheDay, JSON_PRETTY_PRINT));
    logMessage("Successfully saved movie data");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    createFallbackMovie();
}

function createFallbackMovie() {
    global $outputFile;
    
    $fallbackMovie = [
        "version" => "1.0",
        "lastUpdated" => date("Y-m-d"),
        "movie" => [
            "id" => 278,
            "title" => "The Shawshank Redemption",
            "tagline" => "Fear can hold you prisoner. Hope can set you free.",
            "overview" => "Framed in the 1940s for the double murder of his wife and her lover, upstanding banker Andy Dufresne begins a new life at the Shawshank prison, where he puts his accounting skills to work for an amoral warden. During his long stretch in prison, Dufresne comes to be admired by the other inmates -- including an older prisoner named Red -- for his integrity and unquenchable sense of hope.",
            "releaseDate" => "1994-09-23",
            "runtime" => 142,
            "voteAverage" => 8.7,
            "posterPath" => "https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg",
            "backdropPath" => "https://image.tmdb.org/t/p/original/kXfqcdQKsToO0OUXHcrrNCHDBzO.jpg",
            "director" => "Frank Darabont",
            "cast" => ["Tim Robbins", "Morgan Freeman", "Bob Gunton", "William Sadler", "Clancy Brown"],
            "genres" => ["Drama", "Crime"]
        ]
    ];
    
    file_put_contents($outputFile, json_encode($fallbackMovie, JSON_PRETTY_PRINT));
    logMessage("Created fallback movie due to API failure");
}

logMessage("Movie fetch process completed");
?>

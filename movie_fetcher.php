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

// Set start date (March 14, 2025)
$startDate = strtotime("2025-03-14");
$currentDate = time();
$daysSinceStart = floor(($currentDate - $startDate) / (60 * 60 * 24));

// If we're before the start date, start from day 0
if ($daysSinceStart < 0) {
    $daysSinceStart = 0;
}

// Calculate which movie to use based on days since start date
$index = $daysSinceStart % count($movies);
$movie = $movies[$index];

logMessage("Selected movie: {$movie['title']} (ID: {$movie['id']}) for day " . ($daysSinceStart + 1) . " since start");

// Rest of your script continues as normal...
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
    
    // If no director found in API, use the one from our list
    if (empty($director) && isset($movie['director'])) {
        $director = $movie['director'];
    }
    
    // Get top 5 cast members
    $cast = [];
    if (isset($movieData['credits']['cast']) && !empty($movieData['credits']['cast'])) {
        $cast = array_slice(array_map(function($actor) {
            return $actor['name'];
        }, $movieData['credits']['cast']), 0, 5);
    }
    
    // Make sure we have a poster path
    $posterPath = "";
    if (!empty($movieData['poster_path'])) {
        $posterPath = "https://image.tmdb.org/t/p/w500" . $movieData['poster_path'];
    }
    
    // Make sure we have a backdrop path
    $backdropPath = "";
    if (!empty($movieData['backdrop_path'])) {
        $backdropPath = "https://image.tmdb.org/t/p/original" . $movieData['backdrop_path'];
    }
    
    // Get genres or use category from our list
    $genres = [];
    if (!empty($movieData['genres'])) {
        $genres = array_map(function($genre) {
            return $genre['name'];
        }, $movieData['genres']);
    } elseif (isset($movie['category'])) {
        $genres = [$movie['category']];
    } else {
        $genres = ["Drama"]; // Default genre
    }
    
    // Format movie data for your app
    $movieOfTheDay = [
        "version" => "1.0",
        "lastUpdated" => date("Y-m-d"),
        "movie" => [
            "id" => $movieData['id'],
            "title" => $movieData['title'],
            "tagline" => $movieData['tagline'] ?? "",
            "overview" => $movieData['overview'] ?? "No overview available.",
            "releaseDate" => $movieData['release_date'] ?? $movie['year'] . "-01-01",
            "runtime" => $movieData['runtime'] ?? 120,
            "voteAverage" => $movieData['vote_average'] ?? 0,
            "posterPath" => $posterPath,
            "backdropPath" => $backdropPath,
            "director" => $director,
            "cast" => $cast,
            "genres" => $genres
        ]
    ];
    
    // Save to JSON file
    file_put_contents($outputFile, json_encode($movieOfTheDay, JSON_PRETTY_PRINT));
    logMessage("Successfully saved movie data for: {$movieData['title']}");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    createFallbackMovie($movie);
}

function createFallbackMovie($selectedMovie) {
    global $outputFile;
    
    // Use selected movie data for fallback if possible
    $movieId = $selectedMovie['id'] ?? 278; // Default to Shawshank Redemption ID if not available
    $title = $selectedMovie['title'] ?? "The Shawshank Redemption";
    $director = $selectedMovie['director'] ?? "Frank Darabont";
    $year = $selectedMovie['year'] ?? "1994";
    $category = $selectedMovie['category'] ?? "Drama";
    
    $fallbackMovie = [
        "version" => "1.0",
        "lastUpdated" => date("Y-m-d"),
        "movie" => [
            "id" => $movieId,
            "title" => $title,
            "tagline" => "No tagline available",
            "overview" => "Unable to fetch movie details from TMDB API. This is a fallback entry based on our movie list.",
            "releaseDate" => $year . "-01-01",
            "runtime" => 120,
            "voteAverage" => 0.0,
            "posterPath" => "",
            "backdropPath" => "",
            "director" => $director,
            "cast" => [],
            "genres" => [$category]
        ]
    ];
    
    file_put_contents($outputFile, json_encode($fallbackMovie, JSON_PRETTY_PRINT));
    logMessage("Created fallback movie due to API failure for: $title");
}

logMessage("Movie fetch process completed");
?>

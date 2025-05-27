<?php include 'includes/header.php'; ?>

<!-- City Banner -->
<div class="jumbotron jumbotron-fluid text-center bg-light p-5 mb-4">
    <div class="container">
        <h1 class="display-4">Discover Your City</h1>
        <p class="lead">Find the best local businesses and services.</p>
    </div>
</div>

<!-- Search Bar -->
<div class="container mb-5">
    <form action="search.php" method="GET"> <!-- Changed action to search.php and method to GET -->
        <div class="row g-3 justify-content-center">
            <div class="col-md-5">
                <input type="text" class="form-control" name="keywords" placeholder="Keywords (e.g., restaurant, plumber, cafe)">
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="location" placeholder="Location (e.g., City Center, Downtown)">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </div>
    </form>
</div>

<!-- Featured Businesses Section -->
<div class="container">
    <h2 class="text-center mb-4">Featured Businesses</h2>
    <p class="text-center text-muted mb-4">Explore some of the top-rated and popular businesses in our directory.</p>
    <div class="row">
        <!-- Placeholder Business Card 1 -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <img src="assets/images/placeholder.jpg" class="card-img-top" alt="Business Image Placeholder" style="height: 200px; object-fit: cover;">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Sample Business 1</h5>
                    <p class="card-text mb-1"><small class="text-muted">Location:</small> City Center</p>
                    <p class="card-text"><small class="text-muted">Category:</small> Restaurant</small></p>
                    <a href="business-detail.php?id=1" class="btn btn-primary mt-auto align-self-start">View Details</a>
                </div>
            </div>
        </div>
        <!-- Placeholder Business Card 2 -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <img src="assets/images/placeholder.jpg" class="card-img-top" alt="Business Image Placeholder" style="height: 200px; object-fit: cover;">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Sample Business 2</h5>
                    <p class="card-text mb-1"><small class="text-muted">Location:</small> Downtown</p>
                    <p class="card-text"><small class="text-muted">Category:</small> Retail Shop</small></p>
                    <a href="business-detail.php?id=2" class="btn btn-primary mt-auto align-self-start">View Details</a>
                </div>
            </div>
        </div>
        <!-- Placeholder Business Card 3 -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <img src="assets/images/placeholder.jpg" class="card-img-top" alt="Business Image Placeholder" style="height: 200px; object-fit: cover;">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Sample Business 3</h5>
                    <p class="card-text mb-1"><small class="text-muted">Location:</small> Suburbia</p>
                    <p class="card-text"><small class="text-muted">Category:</small> Service Provider</small></p>
                    <a href="business-detail.php?id=3" class="btn btn-primary mt-auto align-self-start">View Details</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

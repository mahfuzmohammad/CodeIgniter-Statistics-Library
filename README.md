# Simple Statistics Library for CodeIgniter
A simple library for CodeIgniter platform to perform simple linear regression, moving average, time series forecast etc.

## How to install
1. Download the git repository.
2. Copy the Statistics.php file to libraries folder of your CodeIgniter project.

## Example
### Time series forecast ( Multiplicative model )

```
$y = array(4.8, 4.1, 6.0, 6.5, 5.8, 5.2, 6.8, 7.4, 6.0, 5.6, 7.5, 7.8, 6.3, 5.9, 8.0, 8.4); // original data
$forecast_number = 4; // number of future data in $y you want to predict
$forecasts = array(); // output array, size will be length of $y + $forecast_number

$this->statistics->time_series_forecast_multiplicative_model($y, $seasons, $forecast_number, $forecasts);
		
echo json_encode($forecasts);
```

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Statistics {

	/**
	 * Returns slop and intercept of simple linear regression
	 * Reference: https://www.easycalculation.com/statistics/learn-regression.php
	 * @param 	array 	$x 		x values
	 * @param 	array 	$y 		y values
	 * @return 	array 			slop and intercept
	 */
	public function simple_linear_regression(&$x, &$y) {

		if( count($x) !== count($y) ) {
			return array(NULL, NULL);
		}

		$n = count($x);
		$sumX = 0; $sumY = 0; $sumXX = 0; $sumXY = 0;

		for( $i = 0; $i < $n; $i++ ) {
			$sumX += $x[$i];
			$sumY += $y[$i];
			$sumXX += $x[$i]*$x[$i];
			$sumXY += $x[$i]*$y[$i];
		}

		$slop = ( $n * $sumXY - $sumX * $sumY ) / ( $n * $sumXX - $sumX * $sumX );
		$intercept = ( $sumY - $slop * $sumX ) / $n;

		return array( $slop, $intercept );
	}


	/**
	 * Time series forecasting section
	 * Note: t = input_index + 1
	 */


	/**
	 * Generates simple moving average MA(p) of given data 
	 * and seasons 'p'
	 *
	 * Note: For n input values and p seasons
	 * there are n-p+1 moving averages
	 *
	 * Reference: https://en.wikipedia.org/wiki/Moving_average
	 * @param 	array 	$y 			input values
	 * @param 	int 	$seasons 	number of seasons to use
	 * @param 	array 	$ma 		moving average output
	 * @return 	void
	 */
	public function moving_average(&$y, $seasons, &$ma) {
		$window_left = 0; $sum = 0;
		$number_of_inputs = count($y);

		for( $window_right = 0; $window_right < $number_of_inputs; $window_right++ ) {

			if( $window_right >= $seasons ) {
				array_push($ma, floatval($sum) / floatval($seasons));
				$sum -= $y[$window_left++];
			}

			$sum += $y[$window_right];

		}

		// process the last moving average
		array_push($ma, floatval($sum) / floatval($seasons));
	}

	/**
	 * Generates centered moving average CMA(p) of given data 
	 * and seasons 'p'
	 *
	 * Note: For n input values and p seasons
	 * there are n-p+1 centered moving averages, where n is odd
	 * there are n-p centered moving averages, where n is even
	 *
	 * Reference: https://en.wikipedia.org/wiki/Moving_average
	 * @param 	array 	$y 			input values
	 * @param 	int 	$seasons 	number of seasons to use
	 * @param 	array 	$cma 		centered moving average output
	 * @return 	void
	 */
	public function centered_moving_average(&$y, $seasons, &$cma) {
		$temp_ma = array();

		if( $seasons % 2 === 0 ) { // even

			$this->moving_average($y, $seasons, $temp_ma);

			// even to further smoothing
			$loop = count($temp_ma);
			for( $i = 1; $i < $loop; $i++ ) {
				array_push( $cma, floatval($temp_ma[$i-1] + $temp_ma[$i]) / 2.0 );
			}

		} else { // odd
			$this->moving_average($y, $seasons, $cma);
		}
	}

	/**
	 * Extracts seasonal component of data 
	 *
	 * @param 	array 	$y 			input values
	 * @param 	int 	$seasons 	number of seasons to use
	 * @param 	array 	$st 		seasonal components output
	 * @return 	void
	 */
	public function seasonal_components(&$y, $seasons, &$st) {
		$cma = array();
		$this->centered_moving_average($y, $seasons, $cma);

		$StIt = array(); // $StIt = Yt / CMA
		$cmaLenght = count($cma);
		$cmaOffset = $seasons / 2;
		// remember, $t = index + 1 in 1 indexed
		$tl = $cmaOffset; // starting $t for CMA, 0-indexed
		$tr = count($y) - $cmaOffset; // ending $t for CMA, 0-indexed

		if( $cmaLenght < $seasons ) return;

		for( $i = 0; $i < $cmaLenght; $i++ ) {
			array_push($StIt, floatval($y[ $tl + $i ]) / floatval($cma[$i]));
		}

		for( $i = 0; $i < $seasons; $i++ ) {
			$sum = 0; $cnt = 0;
			for( $j = $i; $j < $tr; $j += $seasons ) {
				if( $j > $tl ) {
					$sum += $StIt[ $j - $cmaOffset ];
					$cnt++;
				}
			}

			array_push($st, floatval($sum) / floatval($cnt));
		}
	}

	/**
	 * Extracts trend component of data 
	 *
	 * @param 	array 	$y 					input values
	 * @param 	array 	$st 				input seasonal components
	 * @param 	int 	$seasons 			number of seasons to use
	 * @param 	int 	$number_of_outputs 	useful for forecasting
	 * @param 	array 	$tt 				trend components output
	 * @return 	void
	 */
	public function trend_components(&$y, &$st, $seasons, $number_of_outputs, &$tt) {

		if (count($st) != $seasons) return;

		// deseasonalize
		$number_of_inputs = count($y);
		$t = array();
		$deseason = array();

		for( $i = 0; $i < $number_of_inputs; $i++ ) {
			array_push( $t, $i+1 );
			array_push( $deseason, floatval($y[$i]) / floatval($st[$i%$seasons]) );
		}

		list( $slop, $intercept ) = $this->simple_linear_regression($t, $deseason);

		// generate trend component
		for( $i = 0; $i < $number_of_outputs; $i++ ) {
			array_push($tt, $intercept + ($i+1) * $slop);
		}
	}

	/**
	 * Forecast  time series data using multiplicative model
	 *
	 * @param 	array 	$y 					input values
	 * @param 	int 	$seasons 			number of seasons to use
	 * @param 	int 	$forecast_number 	how many points ahead
	 *										needed to be forcasted
	 * @param 	array 	$output 			output
	 * @return 	void
	 */
	public function time_series_forecast_multiplicative_model(&$y, $seasons, $forecast_number, &$output) {

		$number_of_inputs = count($y);
		$number_of_outputs = $number_of_inputs + $forecast_number;

		$st = array();
		$tt = array();
		$forecasts = array();

		$this->seasonal_components($y, $seasons, $st);
		$this->trend_components($y, $st, $seasons, $number_of_outputs, $tt);

		if( count($st) >= $seasons ) {
			for( $i = 0; $i < $number_of_outputs; $i++ ) {
				array_push($forecasts, $tt[$i] * $st[ $i % $seasons ] );
			}
		}

		$output = array(
			"seasonal_components" => $st,
			"trend_components" => $tt,
			"forecasts" => $forecasts
		);
	}

	/**
	 * Calculates Mean Absolute Percent Error (MAPE)
	 *
	 * @param 	array 	$real		real training data
	 * @param 	array 	$output		output from model for same input
	 * 								parameters of training data
	 */
	public function mean_absolute_percent_error(&$real, &$output) {
		$number_of_inputs = count($real);
		$number_of_outputs = count($output);
		$error = 0.0;

		if( $number_of_inputs <= $number_of_outputs ) {
			for( $i = 0; $i < $number_of_inputs; $i++ ) {

				// invalid
				if( abs($real[$i]) == 0 ) {
					$error = NAN;
					break;
				}

				$error += ( abs($real[$i] - $output[$i]) / abs($real[$i]) );
			}

			$error /= floatval($number_of_inputs);
			$error *= 100.0;
		}

		return $error;
	}
}

?>
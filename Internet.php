<?php


class Internet {

    /**
     * Send a http(s) request
     * @param $strUrl
     * @param string $strRawBody (optional)
     * @param array $arrHeaders (optional)
     * @param int $nConnectTimeout (optional)
     * @param int $nTimeout (optional)
     * @return array ( $strResponseHttpCode, $strResponseBody, $arrResponseHeaders, $strResponseHeaderHttp, $strErrMsg )
     */
    static function curl( $strUrl, $strRawBody = '', $arrHeaders = null, $nConnectTimeout = 2, $nTimeout = 10 ) {
        $strResponseBody = $strErrMsg = $strResponseHttpCode = $strResponseHeaderHttp = '';
        $arrResponseHeaders = array();

        $ch = curl_init( $strUrl );
        if ( $arrHeaders ) curl_setopt( $ch, CURLOPT_HTTPHEADER, $arrHeader );
        if ( $strRawBody ) curl_setopt( $ch, CURLOPT_POSTFIELDS, $strRawBody );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $nConnectTimeout );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $nTimeout );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HEADER, 1 );
        $strResponse = curl_exec( $ch );
        $strResponseHttpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

        if ( $strResponse ) {
            $nHeaderSize = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
            $strResponseHeader = trim( substr( $strResponse, 0, $nHeaderSize ) );
            $strResponseBody = substr( $strResponse, $nHeaderSize );
            $arrResponseHeaders = explode( "\n", $strResponseHeader );
            foreach ( $arrResponseHeaders as $strLine ) {
                if ( stripos( $strLine, 'HTTP' ) === 0 ) {
                    $strResponseHeaderHttp = $strLine;
                    break;
                }
            }
        }

        if ( $errno = curl_errno( $ch ) ) {
            $strErrMsg = "CURL ERR $errno: " . curl_strerror( $errno );

        } elseif ( ! $strResponse ) {
            $strErrMsg = 'NO DATA';
        }
        curl_close( $ch );

        return array( $strResponseHttpCode, $strResponseBody, $arrResponseHeaders, $strResponseHeaderHttp, $strErrMsg );
    }
}

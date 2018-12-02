<?php

class HTMLPurifier_URIFilter_CleanGoogleDocsURL extends HTMLPurifier_URIFilter
{

    public $name = 'CleanGoogleDocsURL';

    public function filter(&$uri, $config, $context)
    {
        if (is_null($uri->host)) {
            return false;
        }
        if ($uri->scheme !== 'image') return true;
        parse_str($uri->query,$query_parsed);
        if ($query_parsed['q']) {
            $url_from_query = parse_url($query_parsed['q']);
            $uri->host = $url_from_query['host'];
            $uri->scheme = $url_from_query['scheme'];
            $uri->path = $url_from_query['path'];
            $uri->query = $url_from_query['query'];
            return true;
        }
        return false;
    }
}

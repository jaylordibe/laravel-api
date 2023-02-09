<?php

namespace App\Models\Custom;

class IpInfo
{

    private string $status;
    private string $country;
    private string $countryCode;
    private string $region;
    private string $regionName;
    private string $city;
    private string $zip;
    private float $latitude;
    private float $longitude;
    private string $timezone;
    private string $isp;
    private string $org;
    private string $as;
    private string $query;

    /**
     * @param string $ip
     */
    public function __construct(string $ip)
    {
        $info = json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));
        $this->status = $info->status ?? '';
        $this->country = $info->country ?? '';
        $this->countryCode = $info->countryCode ?? '';
        $this->region = $info->region ?? '';
        $this->regionName = $info->regionName ?? '';
        $this->city = $info->city ?? '';
        $this->zip = $info->zip ?? '';
        $this->latitude = $info->lat ?? 0;
        $this->longitude = $info->lon ?? 0;
        $this->timezone = $info->timezone ?? '';
        $this->isp = $info->isp ?? '';
        $this->org = $info->org ?? '';
        $this->as = $info->as ?? '';
        $this->query = $info->query ?? '';
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->country);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     */
    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    /**
     * @return string
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * @param string $region
     */
    public function setRegion(string $region): void
    {
        $this->region = $region;
    }

    /**
     * @return string
     */
    public function getRegionName(): string
    {
        return $this->regionName;
    }

    /**
     * @param string $regionName
     */
    public function setRegionName(string $regionName): void
    {
        $this->regionName = $regionName;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getZip(): string
    {
        return $this->zip;
    }

    /**
     * @param string $zip
     */
    public function setZip(string $zip): void
    {
        $this->zip = $zip;
    }

    /**
     * @return float|int
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param float|int $latitude
     */
    public function setLatitude($latitude): void
    {
        $this->latitude = $latitude;
    }

    /**
     * @return float|int
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param float|int $longitude
     */
    public function setLongitude($longitude): void
    {
        $this->longitude = $longitude;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * @param string $timezone
     */
    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return string
     */
    public function getIsp(): string
    {
        return $this->isp;
    }

    /**
     * @param string $isp
     */
    public function setIsp(string $isp): void
    {
        $this->isp = $isp;
    }

    /**
     * @return string
     */
    public function getOrg(): string
    {
        return $this->org;
    }

    /**
     * @param string $org
     */
    public function setOrg(string $org): void
    {
        $this->org = $org;
    }

    /**
     * @return string
     */
    public function getAs(): string
    {
        return $this->as;
    }

    /**
     * @param string $as
     */
    public function setAs(string $as): void
    {
        $this->as = $as;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @param string $query
     */
    public function setQuery(string $query): void
    {
        $this->query = $query;
    }
}

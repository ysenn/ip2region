<?php
namespace IP2Region;


use Exception;
use IP2Region\Exceptions\IP2RegionException;

class IP2Region
{
    /**
     * ip2location
     * 该数据源与xdb数据不同
     * 数据源 https://lite.ip2location.com/database-download
     * @param string $ip
     * @return null|array
     * */
    static function ip2location(string $ip): ?array
    {
        try {
            $db = new \IP2Location\Database(
                self::dbFile('ip2location.bin'), \IP2Location\Database::FILE_IO);
            $recode = $db->lookup($ip, \IP2Location\Database::ALL);
            if ($recode) {
                return [
                    'ip' => $recode['ipAddress'],
                    'iso' => $recode['countryCode'],
                    'country' => $recode['countryName'],
                    'region' => $recode['regionName'],
                    'city' => $recode['cityName'],
                    'location' => [
                        'latitude' => $recode['latitude'],
                        'longitude' => $recode['longitude'],
                    ],
                ];
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    private static $vIndex = null;

    private static $dbBuff = null;

    /**
     * 完全基于文件的查询
     * @param string $ip
     * @return array|null
     * @throws IP2RegionException
     */
    static function withFile(string $ip): ?array
    {
        try {
            $provider = XdbSearcher::newWithFileOnly(self::dbFile());
            $result = $provider->search($ip);
            $provider->close();
            return self::parseData($result);
        } catch (Exception $e) {
            throw new IP2RegionException($e);
        }
    }

    /**
     * 预加载VectorIndex索引
     * @return string|null
     * @throws IP2RegionException
     */
    static function loadVectorIndex(): ?string
    {
        try {
            return XdbSearcher::loadVectorIndexFromFile(self::dbFile());
        } catch (Exception $e) {
            throw new IP2RegionException($e);
        }
    }

    /**
     * 通过VectorIndex索引检索IP信息
     * $vectorIndex 如果不传则单例加载 VectorIndex 索引
     * 项目常驻内存运行时建议在项目启动时通过 loadVectorIndex 方法全局加载调用
     * @param string $ip
     * @param string|null $vectorIndex vectorIndex索引
     * @return array|null
     * @throws IP2RegionException
     */
    static function withVectorIndex(string $ip, ?string $vectorIndex = null): ?array
    {
        try {
            if (is_null($vectorIndex) && is_null(self::$vIndex)) {
                self::$vIndex = XdbSearcher::loadVectorIndexFromFile(self::dbFile());
            }
            $provider = XdbSearcher::newWithVectorIndex(self::dbFile(), $vectorIndex ?: self::$vIndex);
            $result = $provider->search($ip);
            $provider->close();
            return self::parseData($result);
        } catch (Exception $e) {
            throw new IP2RegionException($e);
        }
    }

    /**
     * 缓存整个数据文件
     * @return string|null
     * @throws IP2RegionException
     */
    static function loadBufferFile(): ?string
    {
        try {
            return XdbSearcher::loadContentFromFile(self::dbFile());
        } catch (Exception $e) {
            throw new IP2RegionException($e);
        }
    }

    /**
     * 通过数据文件缓存检索IP信息
     * 未传 $buffer 时则通过单例加载数据缓存
     * 项目常驻内存运行时建议在项目启动时通过 loadBufferFile 方法全局加载调用
     * @param string $ip
     * @param string|null $buffer
     * @return array|null
     * @throws IP2RegionException
     */
    static function withBuffer(string $ip, ?string $buffer = null): ?array
    {
        try {
            if (is_null($buffer) && is_null(self::$dbBuff)) {
                self::$dbBuff = XdbSearcher::loadContentFromFile(self::dbFile());
            }
            $provider = XdbSearcher::newWithBuffer($buffer ?: self::$dbBuff);
            $result = $provider->search($ip);
            $provider->close();
            return self::parseData($result);
        } catch (Exception $e) {
            throw new IP2RegionException($e);
        }
    }

    /**
     * 默认xdb文件路径
     * @param string $file 数据文件
     * @return string
     */
    private static function dbFile(string $file = ''): string
    {
        return sprintf(
            '%s%s%s%s%s',
            __DIR__, DIRECTORY_SEPARATOR, 'data', DIRECTORY_SEPARATOR, $file ?: 'ip2region.xdb');
    }

    /**
     * 解析数据
     * @param string|null $data
     * @return array|null
     */
    private static function parseData(?string $data): ?array
    {
        if (!is_null($data)) {
            $dataArr = explode('|', $data);
            return [
                'country' => $dataArr[0] ?: '',
                'region' => $dataArr[1] ?: '',
                'province' => $dataArr[2] ?: '',
                'city' => $dataArr[3] ?: '',
                'isp' => $dataArr[4] ?: '',
            ];
        }
        return null;
    }

}
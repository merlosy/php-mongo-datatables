<?php namespace Merlosy\MongoDatatables;

/**
 * Laravel Datatable Bundle
 * 
 * This bundle is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 * based on Datatables v1.3.3
 * 
 * @package Laravel
 * @category Bundle
 * @version 0.1.0
 * @author Jeremy Legros
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;
use Bllim\Datatables\Datatables;

class MongoDatatables extends Datatables {

    /**
     * Gets query and returns instance of class
     *
     * @return null
     */
    public static function of( $query )
    {
        $ins = new static;
        $ins->save_query($query);
        return $ins;
    }

    /**
     *  Gets results from prepared query
     *
     *  @return null
     */
    protected function get_result()
    {
        if($this->query_type == 'eloquent' || $this->query_type == 'moloquent')
        {
            $this->result_object = $this->query->get();
            if ( $this->query_type == 'moloquent' ) {
                
                $i = 0;
                foreach ($this->result_object as $json_object) {
                    $sorted = array();
                    $object = (array) json_decode($json_object);

                    // sort fields based on columns order
                    foreach ($this->columns as $field) {
                        $sorted[$field] = isset( $object[$field] )? $object[$field] : '';
                    }
                    // $this->result_array[$i] = (array) json_decode($json_object);
                    $this->result_array[$i] = $sorted;
                    $i++;
                }

            }
            else
                $this->result_array = $this->result_object->toArray();
        }
        else
        {
            $this->result_object = $this->query->get();
            $this->result_array = array_map(function($object) { return (array) $object; }, $this->result_object);
        }
    }

    /**
     *  Saves given query and determines its type
     *
     *  @return null
     */

    protected function save_query($query)
    {
        $this->query = $query;
        if ( $query instanceof \Illuminate\Database\Query\Builder) {
            $this->query_type = 'fluent';
            $this->columns = $this->query->columns;
        }
        else if ( $query instanceof \Jenssegers\Mongodb\Eloquent\Builder ) {
            $this->query_type = 'moloquent';
            $this->columns = $this->query->getQuery()->columns;
        } 
        else if ( $query instanceof \Illuminate\Database\Eloquent\Builder ) {
            $this->query_type = 'eloquent';
            $this->columns = $this->query->getQuery()->columns;
        }
    }

    

    /**
     *  Counts current query
     *  @param string $count variable to store to 'count_all' for iTotalRecords, 'display_all' for iTotalDisplayRecords
     *  @return null
     */

    protected function count($count  = 'count_all')
    {
        //Get columns to temp var.
        if($this->query_type == 'eloquent') {
            $query = $this->query->getQuery();
            $connection = $this->query->getModel()->getConnection()->getName();
        }
        else if ( $this->query_type == 'moloquent' ) {
            $query = $this->query->getQuery();
            $connection = $this->query->getModel()->getConnection()->getDriverName();
        } 
        else {
            $query = $this->query;
            $connection = $query->getConnection()->getName();
        }

        $myQuery = clone $query;

        if ( $this->query_type == 'moloquent' ) {
            $this->$count = $myQuery->setBindings($myQuery->getBindings())->count();
        }
        else {

            // if its a normal query ( no union ) replace the slect with static text to improve performance
            if( !preg_match( '/UNION/i', $myQuery->toSql() ) ){
                $myQuery->select( DB::raw("'1' as row") );     
                
                // if query has "having" clause add select columns
                if ($myQuery->havings) {
                    foreach($myQuery->havings as $having) {
                        if (isset($having['column'])) {
                            $myQuery->addSelect($having['column']);
                        } else {
                            // search filter_columns for query string to get column name from an array key
                            $found = false;
                            foreach($this->filter_columns as $column => $val) {
                                if ($val['parameters'][0] == $having['sql'])
                                {
                                    $found = $column;
                                    break;
                                }
                            }
                            // then correct it if it's an alias and add to columns
                            if ($found!==false) {
                                foreach($this->columns as $val) {
                                    $arr = explode(' as ',$val);
                                    if (isset($arr[1]) && $arr[1]==$found)
                                    {
                                        $found = $arr[0];
                                        break;
                                    }
                                }
                                $myQuery->addSelect($found);
                            }
                        }
                    }
                }
            }

            $this->$count = DB::connection($connection)
            ->table(DB::raw('('.$myQuery->toSql().') AS count_row_table'))
            ->setBindings($myQuery->getBindings())->count();
        }

    }


}
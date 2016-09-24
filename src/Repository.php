<?php namespace WebConfection\LaravelRepositories;

use Illuminate\Container\Container as App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use WebConfection\LaravelRepositories\Interfaces\AbstractInterface;
use WebConfection\LaravelRepositories\Exceptions\RepositoryException;
use WebConfection\LaravelRepositories\Traits\ParameterTrait;
use WebConfection\LaravelRepositories\Criteria\OrderByCriteria;

abstract class Repository {

    use ParameterTrait;
    
    /**
     * @var Illuminate\Container\Container
     */
    private $app;

    /**
     * Instance of the model associated to the current repository.
     *
     * @var Illuminate\Database\Eloquent\Model
     */
    private $model = null;

    /**
     * Array of criteria models to be applied to the query.
     *
     * @var array
     */
    private $criteria = [];

    /**
     * Array of strings containing nested data requirements applied to 
     * the query.
     *
     * @var array
     */
    private $nestedData = [];

    /**
     * The query property retrieved from the associated model.
     *
     * @var model
     */    
    private $query = null;

    /**
     * The number of rows in a paginated list
     *
     * @var integer
     */    
    private $rows = 10;

    /**
     * The columns to be returned
     *
     * @var string
     */    
    private $columns = '*';

    /**
     * Flag to deltermine of the current model uses the softDelete trait enabling us to
     * retrieved "trashed" data.
     *
     * @var boolean
     */    
    protected $softdeletes = false;
    
    /**
     * Must be called by any repository that extends this class.
     *
     * @param  Illuminate\Container\Container $app
     * @return  void
     */
    public function __construct( App $app ){

        $this->app = $app;
        $this->makeModel();

        $modelTraits = class_uses( $this->getModel() );
        $this->softDeletes = array_key_exists( 'Illuminate\Database\Eloquent\SoftDeletes', $modelTraits );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     * 
     * @return string
     */
    abstract function model();
    
    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function all( $withTrash = false )
    {
        $this->applyCriteria(); // Apply any criteria
        $this->applyNestedData(); // Include any nested data

        if( $withTrash && $this->softDeletes )
        {
            return $this->getQuery()->withTrashed()->get( $this->getColumns() );
        }
        else
        {
            return $this->getQuery()->get( $this->getColumns() );
        }
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function paginate( $withTrash = false )
    {
        $this->applyCriteria(); // Apply any criteria
        $this->applyNestedData(); // Include any nested data

        if( $withTrash && $this->softDeletes )
        {
            return $this->getQuery()->withTrashed()->paginate( $this->getRows(), $this->getColumns() );    
        }
        else
        {
            return $this->getQuery()->paginate( $this->getRows(), $this->getColumns() );
        }
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function create( array $data ) 
    {
        return $this->setModel( $this->getModel()->create( $data ) );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function update( $id, array $data ) 
    {
        $this->setModel( $this->getModel()->findOrFail( $id ) );

        return $this->getModel()->update( $data );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function delete( $id )
    {
        return $this->getModel()->findOrFail( $id )->destroy( $id );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function forceDelete( $id )
    {
        return $this->getModel()->findOrFail( $id )->forceDelete( $id );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function find( $id )
    {
        $this->setQuery( $this->getModel()->newQuery() ); // Fresh query
        $this->applyNestedData(); // Include any nested data

        if( $this->softDeletes )
        {
            return $this->getQuery()->withTrashed()->findOrFail( $id, $this->getColumns() );
        }
        else
        {
            return $this->getQuery()->findOrFail( $id, $this->getColumns() );
        }
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function findBy( $attributes, $value ) 
    {
        $this->setQuery( $this->getModel()->newQuery() );
        $this->applyNestedData(); // Include any nested data
        return $this->getQuery()->where( $attribute, '=', $value )->first( $this->getColumns() );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function first() 
    {
        $this->applyCriteria(); // Apply any criteria
        $this->applyNestedData(); // Include any nested data

        return $this->getQuery()->first( $this->getColumns() );
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function count()
    {
        $this->applyCriteria(); // Apply any criteria

        return $this->getQuery()->count();
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function lists($value, $key, $distinct = false )
    {
        $this->applyCriteria();

        if( $distinct )
        {
            return $this->getQuery()->distinct()->lists( $value, $key );  
        }
        else
        {
            return $this->getQuery()->lists( $value, $key );
        }
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function setNestedData( array $nestedData )
    {
        $this->nestedData = $nestedData;

        return $this;
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function getNestedData()
    {
        return $this->nestedData;
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    private function applyNestedData()
    {
        foreach( $this->getNestedData() as $nestedData )
        {
            $this->setQuery( $this->getQuery()->with( $nestedData ) );
        }

        return $this;   
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function setQuery( Builder $query )
    {
      $this->query = $query;

      return $this;
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function getQuery()
    {
      return $this->query;
    }

    /**
     * Create an instance of the associated model
     * 
     * @return $this
     * @throws RepositoryException
     */
    private function makeModel() {
        
        $model = $this->app->make( $this->model() );
 
        if ( !$model instanceof Model )
            throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");

        $this->setModel( $model );

        return $this;
    }

    /**
     * Standard setter
     * 
     * @param \Illuminate\Database\Eloquent\Model
     * @return  $this
     */
    public function setModel( Model $model )
    {
      $this->model = $model;

      return $this;
    }

    /**
     * See WebConfection\Illuminate\Interfaces\AbstractInterface
     */
    public function getModel()
    {
      return $this->model;
    }

    /**
     * Return the name of the current class without it's namespacing.
     *
     * @return  string
     */
    public function getModelName()
    {
      $function = new \ReflectionClass( $this->model() );      
      return $function->getShortName();
    }

    /**
     * Standard getter
     * 
     * @return mixed
     */
    public function getCriteria() 
    {
        return $this->criteria;
    }
        
    /**
     * Push criteria onto the class property
     * 
     * @param Criteria $criteria
     * @return $this
     */
    public function pushCriteria( $criteria ) 
    {
        array_push( $this->criteria, $criteria );

        return $this;
    }

    /**
     * Push criteria onto the class property
     * 
     * @param Criteria $criteria
     * @return $this
     */
    public function setOrder( $criteria ) 
    {
        $this->pushCriteria( new OrderByCriteria( ['column' => key( $criteria ), 'direction' => array_shift( $criteria ) ]) );

        return $this;
    }

    /**
     * Standard setter for class property
     * 
     * @param Integer Number of rows
     * @return $this
     */
    public function setRows( $rows )
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Standard getter for class property $rows
     * 
     * @return integer $rows
     */
    private function getRows()
    {
        return (integer)$this->rows;
    }

   /**
     * Standard setter for class property
     * 
     * @param Integer Columns to be returned
     * @return $this
     */
    public function setColumns( $columns )
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Standard getter for class property $columns
     * 
     * @return integer $rows
     */
    private function getColumns()
    {
        return (integer)$this->columns;
    }

    /**
     * Apply the criteria to the current query.
     * 
     * @return $this
     */
    private function applyCriteria()
    {
        $this->setQuery( $this->getModel()->newQuery() );

        foreach( $this->getCriteria() as $criteria ) 
        {
            $this->setQuery( $criteria->apply( $this->getQuery(), $this) );
        }

        return $this;
    }


}
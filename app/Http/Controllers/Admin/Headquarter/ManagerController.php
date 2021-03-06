<?php

namespace App\Http\Controllers\Admin\Headquarter;

use App\Http\Requests\CreateManagerRequest;
use App\Http\Requests\UpdateManagerRequest;
use App\Repositories\ManagerRepository;
//use App\Repositories\RoleRepository;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\Request;
use Flash;
use Prettus\Repository\Criteria\RequestCriteria;
use Response;
use Hash;


class ManagerController extends AppBaseController
{
    /** @var  managerRepository */
    private $managerRepository;



    public function __construct(ManagerRepository $managerRepo)
    {
        $this->managerRepository = $managerRepo;

    }

    //操作后跳转
    private function redirect_url(){
        if(session()->has('manager_index'.admin()->id)){
            return redirect(session('manager_index'.admin()->id));
        }else{
            return redirect(route('managers.index'));
        }
    }

    /**
     * Display a listing of the Shop.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $this->managerRepository->pushCriteria(new RequestCriteria($request));

        session()->put('manager_index'.admin()->id,$request->fullUrl());

        $input=$request->all();

        if(!array_key_exists('type',$input)){
            $input['type'] = '代理商';
        }

        $managers = $this->managerRepository->allManager($input['type']);

        return view('headquarter.managers.index')
              ->with('managers', $managers)
              ->with('input',$input);
    }

    /**
     * Show the form for creating a new Shop.
     *
     * @return Response
     */
    public function create(Request $request)
    {
	    $input=$request->all();

        if(!array_key_exists('type',$input) || array_key_exists('type',$input) && $input['type'] == '代理商'){
            $input['type'] = '代理商';
            session()->forget('admin_account'.admin()->id);
        }

        if(array_key_exists('type',$input) && $input['type'] == '管理员'){
            session()->put('admin_account'.admin()->id,'zcjy');
        }
        $provinces = app('commonRepo')->cityRepo()->getLevelNumCities(1,true);
        $cities = app('commonRepo')->cityRepo()->getLevelNumCities(2,true);
        return view('headquarter.managers.create',compact('input','provinces','cities'));
    }

    /**
     * Store a newly created Shop in storage.
     *
     * @param CreatemanagerRequest $request
     *
     * @return Response
     */
    public function store(CreatemanagerRequest $request)
    {
        $input = $request->all();
       // dd(session()->has('admin_account'.admin()->id));
        if(session()->has('admin_account'.admin()->id)){
            $input['account'] = session('admin_account'.admin()->id);
            $varify = app('commonRepo')->accountInfoRepo()->accountAdminInfo($input['account']);
        }
         else{
            #在这里处理代理商的省代和市代
            #如果选择了代理
            if(array_key_exists('whether_agency',$input) && !empty($input['whether_agency'])){
                #加省级代理
                if($input['fanwei_agency'] == 'province'){
                    if(array_key_exists('province_id',$input) && !empty($input['province_id'])){
                        $input['province'] = $input['province_id'];
                    }
                    else{
                         return redirect(route('managers.create'))
                            ->withErrors('请选择对应的省份!')
                            ->withInput($input);
                    }
                   
                    $input['city'] = 0;
                } #加市级代理
                else{
                    if(array_key_exists('city_id',$input) && !empty($input['city_id'])){
                        $input['city'] = $input['city_id'];
                    }
                    else{
                        return redirect(route('managers.create'))
                            ->withErrors('请选择对应的城市!')
                            ->withInput($input);
                    }
                    $input['province'] = app('commonRepo')->cityRepo()->getLastCity($input['city'])->id;
                }
            }

            $varify = app('commonRepo')->accountInfoRepo()->accountAdminInfo($input['account'],true);

            if(!$varify){
                 return redirect(route('managers.create'))
                            ->withErrors('account信息已存在,请重新输入!')
                            ->withInput($input);
            }

        }

        $input['account_id'] = $varify->id;
        $input['password'] = Hash::make($input['password']);
        $input['parent_id'] = admin()->id;
        //dd($input);
        $manager = $this->managerRepository->model()::create($input);

        #为对应账户信息更新信息
        app('commonRepo')->accountInfoRepo()->model()::where('account',$manager->account)->update([
            'province' => $input['province'],
            'city' => $input['city']
        ]);

        sendGroupNotice(notice_template('添加',$manager->type.$manager->nickname),null,'操作消息');

        Flash::success('创建成功');

       return $this->redirect_url();
    }

    /**
     * Display the specified Shop.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $manager = $this->managerRepository->findWithoutFail($id);

        if (empty($manager)) {
            Flash::error('管理员信息不存在');

            return redirect(route('managers.index'));
        }

        return view('headquarter.managers.show')->with('manager', $manager);
    }

    /**
     * Show the form for editing the specified Shop.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function edit(Request $request,$id)
    {
        $manager = $this->managerRepository->findWithoutFail($id);
        $input=$request->all();

        if(!array_key_exists('type',$input)){
            $input['type'] = '代理商';
        }
        if (empty($manager)) {
            Flash::error('管理员信息不存在');

            return redirect(route('managers.index'));
        }

        $provinces = app('commonRepo')->cityRepo()->getLevelNumCities(1,true);
        $cities = app('commonRepo')->cityRepo()->getLevelNumCities(2,true);

        return view('headquarter.managers.edit')
            ->with('manager', $manager)
            ->with('roles', [])
            ->with('selectedRoles', [])
            ->with('input',$input)
            ->with('provinces',$provinces)
            ->with('cities',$cities);
    }

    /**
     * Update the specified Shop in storage.
     *
     * @param  int              $id
     * @param UpdatemanagerRequest $request
     *
     * @return Response
     */
    public function update($id, UpdatemanagerRequest $request)
    {
        $manager = $this->managerRepository->findWithoutFail($id);

        if (empty($manager)) {
            Flash::error('管理员信息不存在');

            return redirect(route('managers.index'));
        }

        $input = $request->all();

        $input = array_filter( $input, function($v, $k) {
            return $v != '';
        }, ARRAY_FILTER_USE_BOTH );

        #如果选择了代理
        if(array_key_exists('whether_agency',$input) && !empty($input['whether_agency'])){
            #加省级代理
            if($input['fanwei_agency'] == 'province'){
                if(array_key_exists('province_id',$input) && !empty($input['province_id'])){
                    $input['province'] = $input['province_id'];
                }
                else{
                     return redirect(route('managers.edit',$id))
                        ->withErrors('请选择对应的省份!')
                        ->withInput($input);
                }
               
                $input['city'] = 0;
            } #加市级代理
            else{
                if(array_key_exists('city_id',$input) && !empty($input['city_id'])){
                    $input['city'] = $input['city_id'];
                }
                else{
                    return redirect(route('managers.edit',$id))
                        ->withErrors('请选择对应的城市!')
                        ->withInput($input);
                }
                $input['province'] = app('commonRepo')->cityRepo()->getLastCity($input['city'])->id;
            }
        }

        if(array_key_exists('password',$input) && !empty($input['password'])){
             $input['password'] = Hash::make($input['password']);
        }

        $manager->update($input);

        if(array_key_exists('whether_agency',$input) && !empty($input['whether_agency'])){
            app('commonRepo')->accountInfoRepo()->model()::where('account',$manager->account)->update([
            'province' => $input['province'],
            'city' => $input['city']
            ]);
        }

        sendGroupNotice(notice_template('更新',$manager->type.$manager->nickname),null,'操作消息');

        Flash::success('更新成功.');

       return $this->redirect_url();
    }

    /**
     * Remove the specified Shop from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $manager = $this->managerRepository->findWithoutFail($id);

        if (empty($manager)) {
            Flash::error('管理员信息不存在');

            return redirect(route('managers.index'));
        }

        $this->managerRepository->delete($id);

        Flash::success('删除成功.');

        sendGroupNotice(notice_template('删除',$manager->type.$manager->nickname),null,'操作消息');

        return $this->redirect_url();
    }
}

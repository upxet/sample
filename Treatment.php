<?php namespace Wms\Site\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Wms\Site\Models\Sedd;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Mail;

class Treatment extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'site:treatment';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    /**
     * Execute the console command. Send a list of unregistered treatments.
     * @return void
     */
    public function fire()
    {
        $rows = Sedd::where(function ($query) {
            $query->whereBetween('created_at', ['2018-09-01', Carbon::today()->toDateString()])->WhereNull('status');
        })->orWhere(function ($query) {
            $query->whereBetween('created_at', ['2018-09-01', Carbon::today()->subDays(2)->toDateString()])->WhereNull('reg_number');
        })->orderBy('created_at')->get();
        
        $xldata = '';
        
        if ($rows->count()) {
        
            $data = [];
            
            foreach ($rows as $row)
                $data[] = [
                    $row->treatment_id,
                    trim(@$row->treatment->surname.' '.@$row->treatment->firstname.' '.@$row->treatment->patronymic),
                    Carbon::parse($row->created_at)->format('d.m.Y H:i'),
                    $row->sedd_id,
                    $row->status ? 'отсутствует уведомление о регистрации' : 'отсутствует уведомление о поступлении'
                ];
            
            $xldata = Excel::create('Treatments', function($excel) use ($data) {
                $excel->setTitle('Реестр незарегистрированных обращений');
                $excel->sheet('Treatments', function ($sheet) use ($data) {
                    $sheet->row(1, ['Номер', 'ФИО', 'Дата и время', 'Идентификатор', 'Статус']);
                    $sheet->fromArray($data, null, 'A2', false, false);
                });
            })->string();
            
            $msg = 'Реестр незарегистрированных обращений в прикреплённом файле';
            
        } else {
        
            $msg = 'Нет незарегистрированных обращений для включения в реестр';
            
        }
        
        Mail::send('wms.form::mail.treatments', ['msg' => $msg], function ($message) use ($xldata) {
            $message->to(['test@mail.ru', 'test2@mail.ru']);
            if ($xldata)
                $message->attachData($xldata, 'Unregistered-Treatments-'.Carbon::today()->toDateString().'.xls');
        });
        
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

}
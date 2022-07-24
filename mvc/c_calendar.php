<?php

class c_calendar extends Controller
{
    function empty_a() {
        Calendar::draw('year',0,0,function(){
            return [];
        });
        return [
            'q' => 'test 111',
        ];
    }

    function a_test() {
    }

    function b_month() {
        return Calendar::draw('month');
    }

}

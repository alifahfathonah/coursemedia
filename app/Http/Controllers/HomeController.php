<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

use App\User;
use App\Course;
use App\CourseCategory;
use App\Chapter;
use App\Like;
use App\Favorite;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        return view('dashboard.details')->with('user', $user);
    }

    // This will display the list of favorite courses
    public function favorites()
    {
        $favorites = Favorite::where('user_id', Auth::id())->take(16)->inRandomOrder()->get();
        $chapter_number = 1;
        return view('dashboard.favorites')->with([
            'favorites'=> $favorites,
            'chapter_number'=> $chapter_number
        ]);
    }

    // This will display the list of one's own courses
    public function my_courses()
    {
        $courses = Course::where('user_id',Auth::id())->take(16)->inRandomOrder()->get();
        $chapter_number = 1;
        return view('dashboard.my_courses')->with([
            'courses'=> $courses,
            'chapter_number'=> $chapter_number
        ]);
    }

    // This will show edit form for a user detail
    public function edit($id)
    {
        $user = User::find($id);
        return view('dashboard.edit')->with('user', $user);
    }

    // This will desplay the list of favorite courses
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'first_name' => 'required|string|max:15',
            'middle_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'university' => 'required|string|max:255',
            'department' => 'required|string|max:255',
        ]);

        $user = User::find($id);
        $user->first_name = $request->input('first_name');
        $user->middle_name = $request->input('middle_name');
        $user->last_name = $request->input('last_name');
        $user->phone = $request->input('phone');
        $user->university = $request->input('university');
        $user->department = $request->input('department');
        $user->save();

        return redirect('/dashboard')->with('success', 'Details updated successfully');
    }

    // The functions below serves for displaying and controlling course pages which should be available 
    // for authenticated users

    // This will display the individual course page
    public function show_course($course_id, $chapter_number){
        $chapters = Chapter::where('course_id', $course_id)->get();
        $course = Course::find($course_id);
        $current_chapter = Chapter::where(['course_id'=>$course_id, 'chapter_number'=>$chapter_number])->get()->first();
        $likes_count = Like::where('course_id', $course_id)->get()->count();
        $check_liked = Like::where(['user_id'=>Auth::id(), 'course_id'=>$course_id])->get()->first();
        if ($check_liked != null) {
            $like_text = 'Unlike';
        } else {
            $like_text = 'Like';
        }
        $favors_count = Favorite::where('course_id', $course_id)->get()->count();
        $check_favor = Favorite::where(['user_id'=>Auth::id(), 'course_id'=>$course_id])->get()->first();
        if ($check_favor != null) {
            $favorite_text = 'Remove from favorites';
        } else {
            $favorite_text = 'Add to favorites';
        }
        return view('courses.course')->with([
            'course'=> $course,
            'chapters'=> $chapters,
            'current_chapter'=> $current_chapter,
            'likes_count'=> $likes_count,
            'favors_count'=> $favors_count,
            'like_text'=> $like_text,
            'favorite_text'=> $favorite_text
        ]);
    }

    // This will display the course create template
    public function create_course(){
        return view('courses.create_course');
    }

    // This will store the created course
    public function store_course(Request $request)
    {
        $this->validate($request, [
            'course_category' => 'required|string|max:255',
            'course_title' => 'required|string|max:255',
            'course_image' => 'required|image|max:1999',
            'course_description' => 'required|string|max:175',
        ]);

        $course = new Course();
        $course->user_id = Auth::id();
        $course_category = CourseCategory::where('course_category', $request->input('course_category'))->get()->first();
        $course->course_category_id = $course_category->id;
        $course->course_title = $request->input('course_title');
        $course->course_image = '';
        $course->course_description = $request->input('course_description');
        $course->save();

        // Handle file upload
        if ($request->hasFile('course_image')) {
            // Get filename with the extention
            $filenameWithExt = $request->file('course_image')->getClientOriginalName();
            // Get just ext
            $extention = $request->file('course_image')->getClientOriginalExtension();
            // Filename to store
            $ciNameToStore = 'course_image_'.Auth::id().'_'.$course->id.'_'.time().'.'.$extention;
            // Upload image
            $path = $request->file('course_image')->storeAs('public\courses\course_images', $ciNameToStore);
        }
        $course->course_image = $ciNameToStore;
        $course->save();

        $chapter = new Chapter();
        $chapter->user_id = Auth::id();
        $chapter->course_id = $course->id;
        $chapter->chapter_number = '1';
        $chapter->chapter_title = 'Introduction';
        $chapter->chapter_body = '';
        $chapter->save();

        return redirect('/dashboard/my-courses')->with('success', 'Course created successfully');
    }

    // This will delete a course
    public function delete_course(Request $request, $course_id) {
        $course = Course::find($course_id);

        // Delete the course image
        $course_image = $course->course_image;
        $path = 'public/courses/course_images/'. $course_image;
        Storage::delete($path);

        // Delete all chapters of the course
        $chapters = Chapter::where('course_id',$course_id)->get();
        foreach ($chapters as $chapter) {
            $chapter->delete();
        }

        // Delete the course
        $course->delete();
        return redirect('/');
    }

    // This will display the chapter create template
    public function create_chapter($course_id) {
        return view('courses.create_chapter')->with('course_id', $course_id);
    }

    // This will update a chapter
    public function store_chapter(Request $request, $course_id){
        $chapter_number_check = Chapter::where(['course_id'=>$course_id,'chapter_number'=>$request->input('chapter_number')])->get()->first();
        if ($chapter_number_check != null) {
            $chapter_number_verfication = 'required|integer|unique:chapters|max:255';
        } else {
            $chapter_number_verfication = 'required|integer|max:255';
        }
        $this->validate($request, [
            'chapter_number' => $chapter_number_verfication,
            'chapter_title' => 'required|string|max:255',
            'chapter_body' => 'required',
            'chapter_handout' => 'mimes:pdf|max:999',
        ]);

        $chapter = new Chapter();
        $chapter->user_id = Auth::id();
        $chapter->course_id = $course_id;
        $chapter->chapter_number = $request->input('chapter_number');
        $chapter->chapter_title = $request->input('chapter_title');
        $chapter->chapter_body = $request->input('chapter_body');
        $chapter->chapter_handout = '';
        $chapter->save();

        // Handle file upload
        if ($request->hasFile('chapter_handout')) {
            // Get filename with the extention
            $filenameWithExt = $request->file('chapter_handout')->getClientOriginalName();
            // Get just ext
            $extention = $request->file('chapter_handout')->getClientOriginalExtension();
            // Filename to store
            $chNameToStore = 'course_'.$course_id.'/chapter_'.$chapter->id.'_handout'.'_'.Auth::id().'_'.time().'.'.$extention;
            // Upload image
            $path = $request->file('chapter_handout')->storeAs('public\courses\course_handouts', $chNameToStore);
            $chapter->chapter_handout = $chNameToStore;
        }
        $chapter->save();

        return redirect('/courses/'.$course_id.'/'.$chapter->chapter_number)->with('success', 'Chapter created successfully');
    }

    // This will display the chapter edit template
    public function edit_chapter($course_id, $chapter_number){
        $chapter = Chapter::where(['course_id'=>$course_id,'chapter_number'=>$chapter_number])->get()->first();
        return view('courses.edit_chapter')->with('chapter', $chapter);
    }

    // This will update a chapter
    public function update_chapter(Request $request, $course_id, $chapter_number) {
        $chapter_number_check = Chapter::where(['course_id'=>$course_id,'chapter_number'=>$request->input('chapter_number')])->get()->first();
        if ($chapter_number_check != null && $request->input('chapter_number')!=$chapter_number) {
            $chapter_number_verfication = 'required|integer|unique:chapters|max:255';
        } else {
            $chapter_number_verfication = 'required|integer|max:255';
        }
        $this->validate($request, [
            'chapter_number' => $chapter_number_verfication,
            'chapter_title' => 'required|string|max:255',
            'chapter_body' => 'required',
            'chapter_handout' => 'mimes:pdf|max:999',
        ]);

        $chapter = Chapter::where(['course_id'=>$course_id,'chapter_number'=>$chapter_number])->get()->first();
        $chapter->chapter_number = $request->input('chapter_number');
        $chapter->chapter_title = $request->input('chapter_title');
        $chapter->chapter_body = $request->input('chapter_body');

        // Handle file upload
        if ($request->hasFile('chapter_handout')) {
            // Delete the chapter handout
            $chapter_handout = $chapter->chapter_handout;
            $path = 'public/courses/course_handouts/'. $chapter_handout;
            Storage::delete($path);

            // Get filename with the extention
            $filenameWithExt = $request->file('chapter_handout')->getClientOriginalName();
            // Get just ext
            $extention = $request->file('chapter_handout')->getClientOriginalExtension();
            // Filename to store
            $chNameToStore = 'course_'.$course_id.'/chapter_'.$chapter->id.'_handout'.'_'.Auth::id().'_'.time().'.'.$extention;
            // Upload image
            $path = $request->file('chapter_handout')->storeAs('public\courses\course_handouts', $chNameToStore);
            $chapter->chapter_handout = $chNameToStore;
        }
        
        $chapter->save();

        return redirect('/courses/'.$course_id.'/'.$chapter->chapter_number)->with('success', 'Chapter updated successfully');
    }

    // This will delete a chapter
    public function delete_chapter(Request $request, $course_id, $chapter_number) {
        $chapter = Chapter::where(['course_id'=>$course_id,'chapter_number'=>$chapter_number])->get()->first();
        $chapter->delete();
        return redirect('/courses/'.$course_id.'/1');
    }

    // This will download the handout of a chapter
    public function download_chapter_handout($course_id, $chapter_number) { 
        $chapter = Chapter::where(['course_id'=>$course_id,'chapter_number'=>$chapter_number])->get()->first();
        $chapter_handout = $chapter->chapter_handout;
        $handout_path = storage_path('app/public/courses/course_handouts/'.$chapter_handout);
        // return (new Response($handout_path,200))->header('Content-Type', 'pdf'); 
        return response()->download($handout_path); 
    }
}
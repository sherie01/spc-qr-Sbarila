<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function showStudentList()
    {
        $students = User::where('userType', 'student')
            ->whereIn('course', ['BSIT', 'BSCS'])
            ->get();

        return view('ADMINISTRATOR.StudentList.ccs_stud_list', compact('students'));
    }

    public function create(Request $request)
    {
        $rules = [
            'name' => 'required',
            'student_id' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'course' => 'required',
            'phone_number' => 'required',
            'birthday' => 'required',
            'address' => 'required',
            'profile_picture' => 'image|required',
            'userType' => 'required|in:student,instructor',
            'password' => 'required',
            'guardian_name' => 'required',
            'guardian_relationship' => 'required',
            'guardian_phone_number' => 'required',
            'guardian_email' => 'required',
        ];

        $validatedData = $request->validate($rules);

        $student = User::create($validatedData);

        return redirect()->route('students.list')->with('success', 'Student created successfully');
    }

    public function edit($id)
    {
        $student = User::findOrFail($id);
        return view('ADMINISTRATOR.StudentList.Edit.edit', compact('student'));
    }

    public function destroy($id)
    {
        $student = User::findOrFail($id);
        $student->delete();
        return redirect()->route('ccs')->with('success', 'Student deleted successfully.');
    }

    public function restore($id)
    {
        $student = User::withTrashed()->findOrFail($id);
        $student->restore();
        return redirect()->route('ccs')->with('success', 'Student restored successfully.');
    }

    // Add this method to your StudentController
    public function checkEnrollment(Request $request)
    {
        $studentId = $request->input('student_id');
        $student = User::where('student_id', $studentId)->first();

        if ($student && $student->stats === 'Enrolled') {
            return response()->json(['enrolled' => true]);
        } else {
            return response()->json(['enrolled' => false]);
        }
    }
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'student_id' => 'required|unique:users,student_id,' . $id,
            'name' => 'required',
            'course' => 'required',
            'gender' => 'required|in:male,female,other',
            'year_level' => 'nullable',
            'stats' => 'nullable',
        ]);

        $student = User::findOrFail($id);
        $student->update($validatedData);

        // Generate QR code data
        $qrData = [
            'ID' => $request->student_id,
            'Name' => $request->name,
            'Course' => $request->course,
            'Year Level' => $request->year_level,
            'Status' => $request->stats,
        ];
        // Generate the QR code and convert it to a base64 string
        $qrCode = QrCode::format('png')->size(200)->generate(implode(';', $qrData));
        $filename = 'qrcode/' . $student->id . '.png'; // Unique filename
        Storage::disk('public')->put($filename, $qrCode);

        // Save QR code data as base64 string in the database
        $student->qr_code = $filename; // Store only filename in database
        $student->save();

        return redirect()->route('ccs')->with('success', 'Student updated successfully.');
    }
}
